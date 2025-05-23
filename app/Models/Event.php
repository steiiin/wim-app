<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{

  protected $fillable = [
    'payload',
    'start',
    'until',
    'is_allday',
    'autotag',
  ];
  protected $casts = [
    'payload' => 'array',
    'is_allday' => 'boolean',
    'start' => 'datetime',
    'until' => 'datetime',
  ];
  protected $hidden = ['created_at', 'updated_at'];

  // #####################################################################

  public function setStartAttribute($value)
  {
    $this->attributes['start'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  public function setUntilAttribute($value)
  {
    $this->attributes['until'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  // #####################################################################

  public function toMonitorArray()
  {
    return array_merge($this->payload, [
      'type' => 'event',
      'time_start' => $this->start,
      'time_end' => $this->until,
      'is_allday' => $this->is_allday,
    ]);
  }

  // #####################################################################

  public function scopeAdmin($query)
  {
    return $query->whereNull('autotag');
  }

  public function scopeActive($query)
  {

    $now = Carbon::now();
    $todayStart = Carbon::today()->startOfDay();
    $todayEnd = Carbon::today()->endOfDay();

    return $query->where(function ($query) use ($now) {
        // Case 1: Event has both start and until.
        $query->whereNotNull('until')
          ->where('start', '<=', $now)
          ->where('until', '>=', $now);
      })
      ->orWhere(function ($query) use ($todayEnd, $now) {
        // Case 2: Event has only a start (until is null) and is NOT all-day.
        $query->whereNull('until')
          ->where('is_allday', 0)
          ->where('start', '<=', $todayEnd)
          ->where('start', '>=', $now);
      })
      ->orWhere(function ($query) use ($todayStart, $todayEnd) {
        // Case 3: Event has only a start (until is null) and IS all-day.
        $query->whereNull('until')
          ->where('is_allday', 1)
          ->whereBetween('start', [$todayStart, $todayEnd]);
      })
      ->orderBy('start', 'desc')
      ->orderBy('payload', 'asc');

  }

  public function scopeImminent($query)
  {

    $tomorrowStart = Carbon::tomorrow()->startOfDay();
    $tomorrowEnd = Carbon::tomorrow()->endOfDay();

    return $query->whereBetween('start', [ $tomorrowStart, $tomorrowEnd ])
      ->orderByRaw("COALESCE(start, until) ASC")
      ->orderBy('payload', 'asc');

  }

  public function scopeUpcoming($query)
  {

    $tomorrowEnd = Carbon::tomorrow()->endOfDay();

    return $query->where('start', '>', $tomorrowEnd)
      ->orderByRaw("COALESCE(start, until) ASC")
      ->orderBy('payload', 'asc');

  }

  public function scopeOutdated($query)
  {

    $yesterdayEnd = Carbon::yesterday()->endOfDay();

    return $query->where(function ($query) use ($yesterdayEnd) {
        // Case 1: Event has both start and until.
        $query->whereNotNull('until')
          ->where('until', '<', $yesterdayEnd);
      })
      ->orWhere(function ($query) use ($yesterdayEnd) {
        // Case 2: Event has only a start (until is null).
        $query->whereNull('until')
          ->where('start', '<', $yesterdayEnd);
      });

  }

}
