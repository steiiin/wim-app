<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Recurring extends Model
{

  public const DailyType = 'daily';
  public const WeeklyType = 'weekly';
  public const MonthlyDayType = 'monthly-day';
  public const MonthlyWeekdayType = 'monthly-weekday';

  protected $fillable = [
    'payload',
    'from',
    'dueto',
    'recurrence_type',
    'weekday',
    'nth_day',
  ];
  protected $casts = [
    'payload' => 'array',
    'from' => 'datetime',
    'dueto' => 'datetime',
  ];
  protected $hidden = ['created_at', 'updated_at'];

  // #####################################################################

  public function setFromAttribute($value)
  {
    $this->attributes['from'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  public function setDuetoAttribute($value)
  {
    $this->attributes['dueto'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  // #####################################################################

  public function toMonitorArray()
  {

    $now = Carbon::now();

    return array_merge($this->payload, [
      'type' => 'task',
      'time_start' => $this->from->setDate($now->year, $now->month, $now->day),
      'time_end' => $this->dueto->setDate($now->year, $now->month, $now->day),
      'is_allday' => false,
    ]);
  }

  // #####################################################################

  public static function getActive()
  {

    $datelessNow = Carbon::now()->setDate(3000, 1, 1);
    $today = Carbon::today();

    return static::where('from', '<=', $datelessNow)
      ->where('dueto', '>=', $datelessNow)
      ->get()
      ->filter(function ($event) use ($today) {
        switch ($event->recurrence_type) {
          case 'daily':
            return true;

          case 'weekly':
            return $today->dayOfWeek === (int) $event->weekday;

          case 'monthly-day':
            if ((int) $event->nth_day === 0) {
              return $today->isLastDayOfMonth();
            }
            return $today->day === (int) $event->nth_day;

          case 'monthly-weekday':
            if ($today->dayOfWeek !== (int) $event->weekday) {
              return false;
            }
            if ((int) $event->nth_day === 0) {
              $nextWeek = $today->copy()->addWeek();
              return $nextWeek->month !== $today->month;
            }
            $occurrence = 0;
            for ($d = 1; $d <= $today->day; $d++) {
              $date = Carbon::create($today->year, $today->month, $d);
              if ($date->dayOfWeek === (int) $event->weekday) {
                $occurrence++;
              }
            }
            return $occurrence === (int) $event->nth_day;

          default:
            return false;
        }
      })
      ->sortBy('dueto')
      ->sortBy('payload')
      ->values();

  }

}
