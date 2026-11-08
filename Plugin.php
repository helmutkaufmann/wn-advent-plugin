<?php namespace Mercator\Advent;

use Backend;
use Mercator\Advent\Models\Calendar;
use System\Classes\PluginBase;

/**
 * Generic advent calendar: one calendar per year, 24 doors,
 * each door holding a repeater of blocks (Winter.Blocks).
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name' => 'Advent',
            'description' => 'Advent calendar per year with 24 doors. Each door holds blocks.',
            'author' => 'Helmut Kaufmann',
            'icon' => 'icon-gift',
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand('mercator.advent.export', \Mercator\Advent\Console\ExportCalendar::class);
        $this->registerConsoleCommand('mercator.advent.import', \Mercator\Advent\Console\ImportCalendar::class);
    }

    public function registerComponents(): array
    {
        return [
            \Mercator\Advent\Components\AdventCalendar::class => 'adventCalendar',
        ];
    }

    public function registerBlocks(): array
    {
        return [
            'mercator_advent_calendar' => '$/mercator/advent/blocks/advent-calendar.block',
        ];
    }

    public function boot()
    {
        // Year dropdown of the block (same pattern as the quiz block).
        \Winter\Pages\Classes\Page::extend(function ($page) {
            $page->addDynamicMethod('getAdventYearOptions', function () {
                return Calendar::orderBy('year', 'desc')->pluck('year', 'year')->all();
            });
        });
    }

    public function registerPermissions()
    {
        return [
            'mercator.advent.manage_calendars' => ['tab' => 'Advent', 'label' => 'Manage advent calendars'],
        ];
    }

    public function registerNavigation()
    {
        return [
            'advent' => [
                'label' => 'Advent',
                'url' => Backend::url('mercator/advent/calendars'),
                'icon' => 'icon-gift',
                'permissions' => ['mercator.advent.*'],
                'order' => 540,
            ],
        ];
    }
}
