<?php namespace Mercator\Advent\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateCalendarsTable extends Migration
{
    public function up()
    {
        Schema::create('mercator_advent_calendars', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->smallInteger('year')->unsigned()->unique();
            $table->string('title');
            $table->text('intro')->nullable();
            $table->text('waiting_message')->nullable();
            $table->mediumText('doors')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mercator_advent_calendars');
    }
}
