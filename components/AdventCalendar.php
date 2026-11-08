<?php namespace Mercator\Advent\Components;

use BackendAuth;
use Carbon\Carbon;
use Cms\Classes\ComponentBase;
use Mercator\Advent\Models\Calendar;

/**
 * Shows the advent calendar of one year.
 * Door content is only written into the page for doors that may already be opened (server time),
 * so closed doors cannot be read by changing the browser clock.
 */
class AdventCalendar extends ComponentBase
{
    protected ?Calendar $calendar = null;
    protected bool $loaded = false;

    public function componentDetails()
    {
        return [
            'name' => 'Advent calendar',
            'description' => 'Shows the advent calendar of one year.',
        ];
    }

    public function defineProperties()
    {
        return [
            'year' => [
                'title' => 'Year',
                'type' => 'string',
                'default' => '',
            ],
            'simulateDate' => [
                'title' => 'Simulate date',
                'description' => 'Optional. Treat this date as today (for tests and previews).',
                'type' => 'string',
                'default' => '',
            ],
            'animation' => [
                'title' => 'Animation',
                'description' => 'none, fly, fade, zoom, drop or flip',
                'type' => 'string',
                'default' => 'none',
            ],
            'randomize' => [
                'title' => 'Random placement',
                'type' => 'checkbox',
                'default' => false,
            ],
            'openPast' => [
                'title' => 'Allow opening earlier doors',
                'type' => 'checkbox',
                'default' => true,
            ],
        ];
    }

    protected function flag(string $name, bool $default): bool
    {
        $value = $this->property($name);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function animation(): string
    {
        $value = (string) $this->property('animation');
        return in_array($value, ['fly', 'fade', 'zoom', 'drop', 'flip'], true) ? $value : '';
    }

    public function year(): int
    {
        $year = (int) $this->property('year');
        return $year > 0 ? $year : (int) date('Y');
    }

    public function calendar(): ?Calendar
    {
        if (!$this->loaded) {
            $this->calendar = Calendar::where('year', $this->year())->first();
            $this->loaded = true;
        }
        return $this->calendar;
    }

    /** Backend users can look behind all doors with ?advent_preview=1. */
    public function isPreview(): bool
    {
        return (bool) request()->query('advent_preview') && BackendAuth::check();
    }

    /** The simulated date from the block, if set and valid. */
    public function simulatedDate(): ?Carbon
    {
        $value = trim((string) $this->property('simulateDate'));
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isSimulated(): bool
    {
        return $this->simulatedDate() !== null;
    }

    public function now(): Carbon
    {
        return $this->simulatedDate() ?: Carbon::now();
    }

    /** Inline style for the closed-door face; only valid hex colours are used. */
    public function flapStyle(): string
    {
        $calendar = $this->calendar();
        $css = '';
        $hex = '/^#[0-9a-fA-F]{3,8}$/';
        if ($calendar && preg_match($hex, (string) $calendar->door_color)) {
            $css .= 'background:' . $calendar->door_color . ';';
        }
        if ($calendar && preg_match($hex, (string) $calendar->door_text_color)) {
            $css .= 'color:' . $calendar->door_text_color . ';--mc-door-text:' . $calendar->door_text_color . ';';
        }
        return $css;
    }

    public function beforeAdvent(): bool
    {
        $calendar = $this->calendar();
        return $calendar && !$this->isPreview() && $this->now()->lt($calendar->advent());
    }

    public function daysToWait(): int
    {
        $calendar = $this->calendar();
        if (!$calendar) {
            return 0;
        }
        return (int) $this->now()->startOfDay()->diffInDays($calendar->advent()->startOfDay());
    }

    public function waitingMessage(): string
    {
        $calendar = $this->calendar();
        $days = $this->daysToWait();
        $date = $calendar->advent()->format('d.m.Y');
        $text = trim((string) $calendar->waiting_message);
        if ($text === '') {
            $text = 'Der Adventskalender öffnet am :date. Noch :days Tage Geduld.';
        }
        return strtr($text, [':date' => $date, ':days' => $days]);
    }

    /** @return array<int, array{day:int,title:string,label:string,unlocked:bool,opens:string,content:array}> */
    protected function imageUrl(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//|^/#', $value)) {
            return $value;
        }
        return url(config('cms.storage.media.path', '/storage/app/media') . '/' . ltrim($value, '/'));
    }

    public function doors(): array
    {
        $calendar = $this->calendar();
        if (!$calendar) {
            return [];
        }

        $now = $this->now();
        $preview = $this->isPreview();
        $out = [];
        foreach (Calendar::normalizeDoors($calendar->doors) as $i => $door) {
            $day = $i + 1;
            $unlocked = $preview || $calendar->isDoorUnlocked($day, $now);
            // Without "open earlier doors" only today's door opens during advent.
            $missed = $unlocked && !$preview && !$this->flag('openPast', true)
                && $now->lt($calendar->opensAt(25)) && $day !== (int) $now->format('j');
            $out[] = [
                'day' => $day,
                'title' => (string) ($door['title'] ?? ''),
                'label' => (string) ($door['label'] ?? ''),
                'image' => $this->imageUrl((string) ($door['image'] ?? '')),
                'credit' => (string) ($door['image_credit'] ?? ''),
                'image_top' => !array_key_exists('image_top', $door) || (bool) $door['image_top'],
                'unlocked' => $unlocked,
                'missed' => $missed,
                'opens' => $calendar->opensAt($day)->format('d.m.Y'),
                // Content of closed doors never leaves the server.
                'content' => $unlocked ? (array) ($door['content'] ?? []) : [],
            ];
        }
        if ($this->flag('randomize', false)) {
            // New random order on every page load.
            shuffle($out);
        }

        return $out;
    }
}
