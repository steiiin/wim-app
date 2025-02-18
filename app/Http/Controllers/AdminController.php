<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Modules\TrashController;
use App\Models\Event;
use App\Models\Info;
use App\Models\Recurring;
use App\Models\Task;
use App\Services\SettingService;
use Inertia\Inertia;

class AdminController extends Controller
{

    public function index()
    {
        return Inertia::render('Admin', [
            'station_name' => SettingService::getStationName(),
            'station_location' => SettingService::getStationLocation(),
            'infos' => Info::all(),
            'events' => Event::all(),
            'tasks' => Task::all(),
            'recurrings' => Recurring::all(),
            'moduleTrash' => array_merge([ 'calendar_link' => SettingService::getModuleTrashLink() ], TrashController::getHealth()),
        ]);
    }

    public function heartbeat()
    {
        return response()->json(['status' => 'alive']);
    }

}
