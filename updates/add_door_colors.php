<?php namespace Mercator\Advent\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddDoorColors extends Migration
{
    public function up()
    {
        Schema::table('mercator_advent_calendars', function ($table) {
            $table->string('door_color', 20)->nullable();
            $table->string('door_text_color', 20)->nullable();
        });
    }

    public function down()
    {
        Schema::table('mercator_advent_calendars', function ($table) {
            $table->dropColumn(['door_color', 'door_text_color']);
        });
    }
}
