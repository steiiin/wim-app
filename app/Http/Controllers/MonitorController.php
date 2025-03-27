<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Info;
use App\Models\Recurring;
use App\Models\Task;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MonitorController extends Controller
{

  public function index()
  {
    return Inertia::render('Monitor', [
      'station_name' => SettingService::getStationName(),
      'station_time' => Carbon::now(),
      'station_location' => SettingService::getStationLocation(),
      'monitor_zoom' => SettingService::getMonitorZoom(),
    ]);
  }

  public function poll()
  {

    $start=hrtime(true);

    $infos = Info::active()->get();

    $activeEvents = Event::active()->get();
    $imminentEvents = Event::imminent()->get();
    $upcomingEvents = Event::upcoming()->get();

    $tasks = Task::active()->get();

    $recurrings = Recurring::getActive();

    $end=hrtime(true);
    $eta=$end-$start;

    return response()->json([
      'infos' => $infos->map(fn($info) => $info->toMonitorArray()),
      'events' => [
        'active' => $activeEvents->map(fn($event) => $event->toMonitorArray()),
        'imminent' => $imminentEvents->map(fn($event) => $event->toMonitorArray()),
        'upcoming' => $upcomingEvents->map(fn($event) => $event->toMonitorArray()),
      ],
      'tasks' => $tasks->map(fn($task) => $task->toMonitorArray()),
      'recurring' => $recurrings->map(fn($recurring) => $recurring->toMonitorArray()),
      'processed' => $eta/1e+6,
    ]);

  }
}
