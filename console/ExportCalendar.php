<?php namespace Mercator\Advent\Console;

use Illuminate\Console\Command;
use Mercator\Advent\Classes\CalendarArchive;
use Mercator\Advent\Models\Calendar;

class ExportCalendar extends Command
{
    protected $name = 'advent:export';
    protected $description = 'Export the calendar of a year as ZIP (calendar.json and images).';

    protected function getArguments()
    {
        return [
            ['year', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Year of the calendar.'],
            ['file', \Symfony\Component\Console\Input\InputArgument::OPTIONAL, 'Target ZIP file, default advent-calendar-<year>.zip in the current directory.'],
        ];
    }

    public function handle()
    {
        $calendar = Calendar::where('year', (int) $this->argument('year'))->first();
        if (!$calendar) {
            $this->error('No calendar for that year.');
            return 1;
        }
        [$zip, $missing] = CalendarArchive::export($calendar);
        $target = $this->argument('file') ?: getcwd() . '/advent-calendar-' . $calendar->year . '.zip';
        rename($zip, $target);
        $this->info("Exported to $target");
        foreach ($missing as $m) {
            $this->warn("File not found: $m");
        }
        return 0;
    }
}
