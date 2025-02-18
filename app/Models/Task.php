<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{

  protected $fillable = [
    'payload',
    'from',
    'dueto',
    'autotag',
  ];
  protected $casts = [
    'payload' => 'array',
    'dueto' => 'datetime',
  ];
  protected $hidden = ['created_at', 'updated_at'];

  // #####################################################################

  public function setDuetoAttribute($value)
  {
    $this->attributes['dueto'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  // #####################################################################

  public function toMonitorArray()
  {
    return array_merge($this->payload, [
      'type' => 'task',
      'time_start' => $this->dueto->startOfDay(),
      'time_end' => $this->dueto,
      'is_allday' => false,
    ]);
  }

  // #####################################################################

  public function scopeActive($query)
  {

    $now = Carbon::now();
    $todayStart = Carbon::today()->startOfDay();
    $todayEnd = Carbon::today()->endOfDay();

    return $query->where(function ($query) use ($now, $todayStart, $todayEnd) {
        // Case 1: Task has only dueto.
        $query->whereNull('from')
          ->whereBetween('dueto', [ $todayStart, $todayEnd ])
          ->where('dueto', '>=', $now);
      })
      ->orWhere(function ($query) use ($todayEnd, $now) {
        // Case 2: Task has from.
        $query->whereNotNull('from')
          ->where('from', '<=', $now)
          ->where('dueto', '>=', $now)
          ->where('dueto', '<=', $todayEnd);
      })
      ->orderBy('dueto', 'asc')
      ->orderBy('payload', 'asc');

  }

}
