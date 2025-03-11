<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Info;
use App\Models\Task;
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

        }
      }
    });

    return back();

  }

  /**
   * API: Called regularly to do background-jobs.
   * @return \Illuminate\Http\Response
   */
  public function doJobs()
  {

    // purge all old elements
    Info::outdated()->delete();
    Event::outdated()->delete();
    Task::outdated()->delete();

    return $this->handleLog('DoJobs', 'Jobs executed.');

  }

}
