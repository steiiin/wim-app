<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingService
{

  const KEY_MODULE_ICAL_SUBSCRIPTIONS = 'module_ical_subscriptions';

  public static function getModuleIcalSubscriptions(): array
  {
    return json_decode(self::loadSetting(self::KEY_MODULE_ICAL_SUBSCRIPTIONS, '[]'), true);
  }

  // Configuration changes and event replacement share the same database lock.
  public static function updateModuleIcalSubscriptions(callable $update): void
  {
    Setting::firstOrCreate(['key' => self::KEY_MODULE_ICAL_SUBSCRIPTIONS], ['value' => '[]']);
    DB::transaction(function () use ($update) {
      $setting = Setting::where('key', self::KEY_MODULE_ICAL_SUBSCRIPTIONS)->lockForUpdate()->firstOrFail();
      $setting->value = json_encode($update(json_decode($setting->value, true)), JSON_THROW_ON_ERROR);
      $setting->save();
    });
  }

  const KEY_LASTUPDATED = 'settings_last_updated';

  public static function getLastUpdated()
  {
    return Cache::get(self::KEY_LASTUPDATED, now()->subYears(20));
  }
  public static function setLastUpdated()
  {
    Cache::put(self::KEY_LASTUPDATED, now());
  }

  // ##########################################################################

  const KEY_STATION_NAME = "station_name";
  public static function getStationName(): string
  {
    return self::loadSetting(self::KEY_STATION_NAME, "Rettungswache");
  }
  public static function setStationName(string $name)
  {
    self::saveSetting(self::KEY_STATION_NAME, $name);
    self::setLastUpdated();
  }

  // ##########################################################################

  const KEY_STATION_LOCATION = "station_location";
  public static function getStationLocation(): Array
  {
    $location = self::loadSetting(self::KEY_STATION_LOCATION, '{"lat":50.940408,"long":6.991183}');
    return json_decode($location, true);
  }
  public static function setStationLocation(float $lat, float $long)
  {
    $location = json_encode([ 'lat' => $lat, 'long' => $long ]);
    self::saveSetting(self::KEY_STATION_LOCATION, $location);
    self::setLastUpdated();
  }

  // ##########################################################################

  const KEY_MONITOR_ZOOM = "monitor_zoom";
  public static function getMonitorZoom(): float
  {
    return (float)self::loadSetting(self::KEY_MONITOR_ZOOM, 1.0);
  }
  public static function setMonitorZoom(float $zoom)
  {
    self::saveSetting(self::KEY_MONITOR_ZOOM, $zoom);
    self::setLastUpdated();
  }

  // ##########################################################################

  const KEY_MODULE_TRASH_LINK = "module_trash_link";
  public static function getModuleTrashLink(): string
  {
    return self::loadSetting(self::KEY_MODULE_TRASH_LINK, '');
  }
  public static function setModuleTrashLink(string $link)
  {
    self::saveSetting(self::KEY_MODULE_TRASH_LINK, $link);
  }

  // ##########################################################################

  const KEY_MODULE_TRASH_FETCHED = "module_trash_fetched";
  public static function getModuleTrashLastFetched(): Carbon|null
  {
    $date = self::loadSetting(self::KEY_MODULE_TRASH_FETCHED, null);
    return empty($date) ? null : Carbon::parse($date)->setTimezone(config('app.timezone'));
  }
  public static function setModuleTrashLastFetched(Carbon $date)
  {
    self::saveSetting(self::KEY_MODULE_TRASH_FETCHED, $date);
  }

  // ##########################################################################

  private static function loadSetting(string $key, $default = null)
  {
    $setting = Setting::where('key', $key)->first();
    return $setting ? $setting->value : $default;
  }
  private static function saveSetting(string $key, $value)
  {
    Setting::updateOrCreate(
      ['key' => $key],
      ['value' => $value]
    );
  }

}
