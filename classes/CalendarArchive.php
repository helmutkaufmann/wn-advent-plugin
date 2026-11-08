<?php namespace Mercator\Advent\Classes;

use Mercator\Advent\Models\Calendar;
use ZipArchive;

/**
 * Export a calendar as a ZIP (calendar.json + the images it uses) and import such a ZIP again.
 *
 * In calendar.json every local image reference is replaced by the portable token `advent-files/<name>`.
 * On import the files go to <media>/advent/<year>/ and the tokens become real paths again.
 */
class CalendarArchive
{
    const FORMAT = 'mercator-advent-calendar';
    const TOKEN = 'advent-files/';
    const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'pdf'];
    const MAX_JSON = 5 * 1024 * 1024;
    const MAX_TOTAL = 150 * 1024 * 1024;

    protected static function mediaDir(): string
    {
        return storage_path('app/' . trim((string) config('cms.storage.media.folder', 'media'), '/'));
    }

    protected static function mediaUrl(): string
    {
        return rtrim((string) config('cms.storage.media.path', '/storage/app/media'), '/');
    }

    /** Local file for a reference found in a door, or null if it is not a local file we know. */
    protected static function resolve(string $ref): ?string
    {
        $plugin = '/plugins/mercator/advent/assets/img/';
        if (str_starts_with($ref, $plugin)) {
            $file = plugins_path('mercator/advent/assets/img/' . substr($ref, strlen($plugin)));
        } elseif (str_starts_with($ref, static::mediaUrl() . '/')) {
            $file = static::mediaDir() . '/' . substr($ref, strlen(static::mediaUrl()) + 1);
        } else {
            return null;
        }
        $real = realpath($file);
        $roots = [realpath(plugins_path('mercator/advent/assets/img')), realpath(static::mediaDir())];
        foreach ($roots as $root) {
            if ($real && $root && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
                return $real;
            }
        }
        return null;
    }

    /**
     * @return array{0:string,1:array} path of the temporary ZIP and the list of references whose file was not found
     */
    public static function export(Calendar $calendar): array
    {
        $doors = Calendar::normalizeDoors($calendar->doors);
        $zipPath = tempnam(sys_get_temp_dir(), 'advent') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the ZIP file.');
        }

        $names = [];    // source file => name in the ZIP
        $used = [];
        $missing = [];
        $addFile = function (string $ref) use (&$names, &$used, &$missing, $zip): ?string {
            $file = static::resolve($ref);
            if ($file === null) {
                $missing[$ref] = true;
                return null;
            }
            if (!isset($names[$file])) {
                $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', basename($file));
                $name = $base;
                for ($n = 2; isset($used[strtolower($name)]); $n++) {
                    $name = pathinfo($base, PATHINFO_FILENAME) . "-$n." . pathinfo($base, PATHINFO_EXTENSION);
                }
                $used[strtolower($name)] = true;
                $names[$file] = $name;
                $zip->addFile($file, 'files/' . $name);
            }
            return static::TOKEN . $names[$file];
        };

        $mediaUrl = static::mediaUrl();
        $walk = function ($value, ?string $key = null) use (&$walk, $addFile, $mediaUrl) {
            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $walk($v, is_string($k) ? $k : null);
                }
                return $out;
            }
            if (!is_string($value) || $value === '') {
                return $value;
            }
            if ($key === 'image' && !preg_match('#^(https?:)?//|^/(plugins|storage)/#', $value)) {
                $value = $mediaUrl . '/' . ltrim($value, '/');   // media library value -> URL
            }
            return preg_replace_callback(
                '#(?:/plugins/mercator/advent/assets/img|' . preg_quote($mediaUrl, '#') . ')/[A-Za-z0-9._/\-]+#',
                fn($m) => $addFile($m[0]) ?? $m[0],
                $value
            );
        };

        $data = [
            'format' => static::FORMAT,
            'version' => 1,
            'year' => (int) $calendar->year,
            'title' => (string) $calendar->title,
            'intro' => $calendar->intro,
            'waiting_message' => $calendar->waiting_message,
            'door_color' => $calendar->door_color,
            'door_text_color' => $calendar->door_text_color,
            'doors' => $walk($doors),
        ];
        $zip->addFromString('calendar.json', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        if ($missing) {
            $zip->addFromString('missing-files.txt', "These files are referenced but were not found on the server and are not in this ZIP:\n" . implode("\n", array_keys($missing)) . "\n");
        }
        $zip->close();

        return [$zipPath, array_keys($missing)];
    }

    /**
     * Delete the image files this calendar keeps in <media>/advent/<year>/ (where imports put them).
     * Files elsewhere (own uploads, plugin images) are never touched. Returns the number of deleted files.
     */
    public static function deleteImages(Calendar $calendar): int
    {
        $dir = static::mediaDir() . '/advent/' . (int) $calendar->year;
        $prefix = static::mediaUrl() . '/advent/' . (int) $calendar->year . '/';
        $json = json_encode(Calendar::normalizeDoors($calendar->doors), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $count = 0;
        if (preg_match_all('#' . preg_quote($prefix, '#') . '([A-Za-z0-9][A-Za-z0-9._-]*)#', (string) $json, $m)) {
            foreach (array_unique($m[1]) as $name) {
                $file = $dir . '/' . $name;
                if (is_file($file) && unlink($file)) {
                    $count++;
                }
            }
        }
        if (is_dir($dir) && count(array_diff(scandir($dir), ['.', '..'])) === 0) {
            rmdir($dir);
        }
        return $count;
    }

    /** Import a ZIP made by export(). $year null = year from the file. */
    public static function import(string $zipPath, ?int $year = null, bool $replace = false): Calendar
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \ApplicationException('This is not a valid ZIP file.');
        }
        try {
            $stat = $zip->statName('calendar.json');
            if (!$stat || $stat['size'] > static::MAX_JSON) {
                throw new \ApplicationException('calendar.json is missing or too large. Is this an exported advent calendar?');
            }
            $data = json_decode((string) $zip->getFromName('calendar.json'), true);
            if (!is_array($data) || ($data['format'] ?? '') !== static::FORMAT || !is_array($data['doors'] ?? null)) {
                throw new \ApplicationException('calendar.json is not an exported advent calendar.');
            }
            $year = $year ?: (int) ($data['year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                throw new \ApplicationException('Please enter a year between 2000 and 2100.');
            }
            $existing = Calendar::where('year', $year)->first();
            if ($existing && !$replace) {
                throw new \ApplicationException("A calendar for $year already exists. Choose another year or tick \"Replace existing calendar\".");
            }

            // Extract the images (flat names, whitelisted extensions, size limit)
            $dir = static::mediaDir() . '/advent/' . $year;
            $total = 0;
            $extracted = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                if (!preg_match('#^files/([A-Za-z0-9][A-Za-z0-9._-]*)$#', $s['name'], $m)) {
                    continue;
                }
                $ext = strtolower(pathinfo($m[1], PATHINFO_EXTENSION));
                $total += $s['size'];
                if (!in_array($ext, static::EXTENSIONS, true)) {
                    continue;
                }
                if ($total > static::MAX_TOTAL) {
                    throw new \ApplicationException('The ZIP contains too much data.');
                }
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Cannot create $dir");
                }
                $in = $zip->getStream($s['name']);
                $out = fopen($dir . '/' . $m[1], 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
                $extracted[$m[1]] = true;
            }

            $url = static::mediaUrl() . '/advent/' . $year . '/';
            $walk = function ($value, ?string $key = null) use (&$walk, $url, $year, $extracted) {
                if (is_array($value)) {
                    $out = [];
                    foreach ($value as $k => $v) {
                        $out[$k] = $walk($v, is_string($k) ? $k : null);
                    }
                    return $out;
                }
                if (!is_string($value)) {
                    return $value;
                }
                if ($key === 'image' && str_starts_with($value, static::TOKEN)) {
                    return '/advent/' . $year . '/' . substr($value, strlen(static::TOKEN));   // media library value
                }
                return preg_replace_callback(
                    '#' . preg_quote(static::TOKEN, '#') . '([A-Za-z0-9][A-Za-z0-9._-]*)#',
                    fn($m) => isset($extracted[$m[1]]) ? $url . $m[1] : $m[0],
                    $value
                );
            };

            $calendar = $existing ?: new Calendar();
            $calendar->year = $year;
            $calendar->title = (string) ($data['title'] ?? "Advent $year");
            $calendar->intro = $data['intro'] ?? null;
            $calendar->waiting_message = $data['waiting_message'] ?? null;
            $calendar->door_color = $data['door_color'] ?? null;
            $calendar->door_text_color = $data['door_text_color'] ?? null;
            $calendar->doors = Calendar::normalizeDoors($walk($data['doors']));
            $calendar->save();
            return $calendar;
        } finally {
            $zip->close();
        }
    }
}
