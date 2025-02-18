<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Info extends Model
{

  protected $fillable = [
    'payload',
    'is_permanent',
    'is_allday',
    'from',
    'until',
    'autotag',
  ];
  protected $casts = [
    'payload' => 'array',
    'is_permanent' => 'boolean',
    'is_allday' => 'boolean',
    'from' => 'datetime',
    'until' => 'datetime',
  ];
  protected $hidden = ['created_at', 'updated_at'];

  // #####################################################################

  public function setFromAttribute($value)
  {
    $this->attributes['from'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  public function setUntilAttribute($value)
  {
    $this->attributes['until'] = empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
  }

  // #####################################################################

  public function toMonitorArray()
  {
    return array_merge($this->payload, [
      'type' => 'info',
    ]);
  }

  // #####################################################################

  public function scopeActive($query)
  {

    $now = Carbon::now();

    return $query->where('is_permanent', true)
      ->orWhere(function ($query) use ($now) {
        $query->where('is_permanent', false)
          ->where('from', '<=', $now)
          ->where('until', '>=', $now);
      })
      ->orderBy('until', 'asc')
      ->orderBy('payload', 'asc');

  }

}
