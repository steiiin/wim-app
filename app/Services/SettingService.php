<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

class SettingService
{

  const KEY_STATION_NAME = "station_name";
  public static function getStationName(): string
  {
    return self::loadSetting(self::KEY_STATION_NAME, "Rettungswache");
  }
  public static function setStationName(string $name)
  {
    self::saveSetting(self::KEY_STATION_NAME, $name);
  }

  // ##########################################################################

  const KEY_STATION_LOCATION = "station_location";
  public static function getStationLocation(): Array
  {
    $location = self::loadSetting(self::KEY_STATION_LOCATION, '{"lat":51.1630871,"long":13.4704939}');
    return json_decode($location, true);
  }
  public static function setStationLocation(float $lat, float $long)
  {
    $location = json_encode([ 'lat' => $lat, 'long' => $long ]);
    self::saveSetting(self::KEY_STATION_LOCATION, $location);
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
