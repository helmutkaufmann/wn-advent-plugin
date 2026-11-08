<?php namespace Mercator\Advent\Models;

use Carbon\Carbon;
use Winter\Storm\Database\Model;

/**
 * One advent calendar for one year.
 * `doors` is a JSON list of exactly 24 items. The position in the list is the day:
 * index 0 = 1 December, index 23 = 24 December.
 */
class Calendar extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $table = 'mercator_advent_calendars';
    public $timestamps = true;

    protected $fillable = ['year', 'title', 'intro', 'waiting_message', 'doors', 'door_color', 'door_text_color'];

    protected $jsonable = ['doors'];

    public $rules = [
        'year' => 'required|integer|min:2000|max:2100|unique:mercator_advent_calendars,year',
        'title' => 'required',
    ];

    /** The advent calendar always has 24 doors: pad or trim before saving. */
    public function beforeSave()
    {
        $this->doors = static::normalizeDoors($this->doors);
    }

    public static function emptyDoor(): array
    {
        return ['title' => '', 'label' => '', 'content' => []];
    }

    public static function normalizeDoors($doors): array
    {
        $doors = is_array($doors) ? array_values($doors) : [];
        $doors = array_slice($doors, 0, 24);
        while (count($doors) < 24) {
            $doors[] = static::emptyDoor();
        }
        return $doors;
    }

    /** First moment (app timezone) at which the given door (1..24) may be opened. */
    public function opensAt(int $day): Carbon
    {
        return Carbon::create((int) $this->year, 12, $day, 0, 0, 0);
    }

    public function isDoorUnlocked(int $day, ?Carbon $now = null): bool
    {
        return ($now ?: Carbon::now())->gte($this->opensAt($day));
    }

    public function advent(): Carbon
    {
        return $this->opensAt(1);
    }
}
