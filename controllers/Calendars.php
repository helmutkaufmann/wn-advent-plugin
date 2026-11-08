<?php namespace Mercator\Advent\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use Mercator\Advent\Classes\CalendarArchive;
use Mercator\Advent\Models\Calendar;

class Calendars extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['mercator.advent.manage_calendars'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Mercator.Advent', 'advent');
        $this->addJs('/plugins/mercator/advent/assets/js/backend-doors.js');
        $this->addCss('/plugins/mercator/advent/assets/css/backend-doors.css');
    }

    /** New calendars start with 24 empty doors and the next advent year. */
    public function formExtendModel($model)
    {
        if (!$model->exists) {
            $model->year = (int) date('Y');
            // Pre-filled titles reflect the day: "1. Dezember / December 1" ... "24. Dezember / December 24".
            $doors = Calendar::normalizeDoors([]);
            foreach ($doors as $i => &$door) {
                $door['title'] = ($i + 1) . '. Dezember / December ' . ($i + 1);
            }
            unset($door);
            $model->doors = $doors;
        }
    }

    /** Download one calendar as ZIP (calendar.json + images). */
    public function export($id = null)
    {
        $calendar = Calendar::findOrFail((int) $id);
        [$zip] = CalendarArchive::export($calendar);
        return response()->download($zip, 'advent-calendar-' . $calendar->year . '.zip')->deleteFileAfterSend(true);
    }

    /** Delete the calendar and the image files it keeps in the media library under advent/<year>. */
    public function onDeleteWithImages($recordId = null)
    {
        $calendar = Calendar::findOrFail((int) $recordId);
        $n = CalendarArchive::deleteImages($calendar);
        $calendar->delete();
        Flash::success("Calendar deleted, $n image file(s) removed.");
        return \Backend::redirect('mercator/advent/calendars');
    }

    public function onLoadImportForm()
    {
        return $this->makePartial('import_form');
    }

    /** Plain form post (file upload): create or replace a calendar from an exported ZIP. */
    public function import()
    {
        try {
            $file = \Input::file('archive');
            if (!$file || !$file->isValid()) {
                throw new \ApplicationException('Please choose a ZIP file.');
            }
            $year = (int) post('year') ?: null;
            $calendar = CalendarArchive::import($file->getRealPath(), $year, (bool) post('replace'));
            Flash::success("Calendar {$calendar->year} imported.");
            return \Backend::redirect('mercator/advent/calendars/update/' . $calendar->id);
        } catch (\Exception $e) {
            Flash::error($e->getMessage());
            return \Backend::redirect('mercator/advent/calendars');
        }
    }
}
