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
    $location = self::loadSetting(self::KEY_STATION_LOCATION, '{"lat":50.940408,"long":6.991183}');
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

  const KEY_MODULE_SHAREPOINT_USER = "module_sharepoint_user";
  public static function getModuleSharepointUser(): string
  {
    return self::loadSetting(self::KEY_MODULE_SHAREPOINT_USER, '');
  }
  public static function setModuleSharepointUser(string $username)
  {
    self::saveSetting(self::KEY_MODULE_SHAREPOINT_USER, $username);
  }

  // ##########################################################################

  const KEY_MODULE_SHAREPOINT_PASS = "module_sharepoint_pass";
  public static function getModuleSharepointPass(): string
  {
    return self::loadSetting(self::KEY_MODULE_SHAREPOINT_PASS, '');
  }
  public static function setModuleSharepointPass(string $password)
  {
    self::saveSetting(self::KEY_MODULE_SHAREPOINT_PASS, $password);
  }

  // ##########################################################################

  const KEY_MODULE_SHAREPOINT_LINK = "module_sharepoint_link";
  public static function getModuleSharepointLink(): string
  {
    return self::loadSetting(self::KEY_MODULE_SHAREPOINT_LINK, '');
  }
  public static function setModuleSharepointLink(string $link)
  {
    self::saveSetting(self::KEY_MODULE_SHAREPOINT_LINK, $link);
  }

  // ##########################################################################

  const KEY_MODULE_SHAREPOINT_FETCHED = "module_sharepoint_fetched";
  public static function getModuleSharepointLastFetched(): Carbon|null
  {
    $date = self::loadSetting(self::KEY_MODULE_SHAREPOINT_FETCHED, null);
    return empty($date) ? null : Carbon::parse($date)->setTimezone(config('app.timezone'));
  }
  public static function setModuleSharepointLastFetched(Carbon $date)
  {
    self::saveSetting(self::KEY_MODULE_SHAREPOINT_FETCHED, $date);
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
