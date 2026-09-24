<?php

namespace App\Http\Controllers;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{

  public function store(Request $request)
  {

    $data = $request->validate([
      'station_name' => 'sometimes|required|string',
      'station_location' => 'sometimes|required|array:lat,long',
      'station_location.lat' => 'required|numeric|between:-90,90',
      'station_location.long' => 'required|numeric|between:-180,180',
      'monitor_zoom' => 'sometimes|required|numeric|between:0.8,1.4',
    ]);

    DB::transaction(function () use ($data)
    {
      foreach ($data as $key => $value)
      {
        switch ($key)
        {

          // station settings
          case 'station_name':
            SettingService::setStationName($value);
            break;

          case 'station_location':
            SettingService::setStationLocation($value['lat'], $value['long']);
            break;

          case 'monitor_zoom':
            SettingService::setMonitorZoom($value);
            break;

        }
      }
    });

    return back();

  }

}
