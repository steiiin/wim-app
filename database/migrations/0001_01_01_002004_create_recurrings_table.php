<?php

use App\Models\Recurring;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurrings', function (Blueprint $table) {
            $table->id();
            $table->mediumText('payload');
            $table->dateTimeTz('from');
            $table->dateTimeTz('dueto');
            $table->enum('recurrence_type', [ Recurring::DailyType, Recurring::WeeklyType, Recurring::MonthlyDayType, Recurring::MonthlyWeekdayType ]);
            $table->integer('weekday')->nullable();
            $table->integer('nth_day')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurrings');
    }
};
