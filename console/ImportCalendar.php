<?php namespace Mercator\Advent\Console;

use Illuminate\Console\Command;
use Mercator\Advent\Classes\CalendarArchive;

class ImportCalendar extends Command
{
    protected $name = 'advent:import';
    protected $description = 'Import a calendar from a ZIP made by advent:export or the backend.';

    protected function getArguments()
    {
        return [['file', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'ZIP file.']];
    }

    protected function getOptions()
    {
        return [
            ['year', null, \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED, 'Target year, default is the year in the file.'],
            ['replace', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Overwrite an existing calendar of that year.'],
        ];
    }

    public function handle()
    {
        try {
            $calendar = CalendarArchive::import($this->argument('file'), $this->option('year') ? (int) $this->option('year') : null, (bool) $this->option('replace'));
        } catch (\ApplicationException $e) {
            $this->error($e->getMessage());
            return 1;
        }
        $this->info("Calendar {$calendar->year} imported (id {$calendar->id}).");
        return 0;
    }
}
