<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\ModuleTrashService;
use App\Services\SettingService;
use Illuminate\Http\Request;

class TrashController extends Controller
{

  /**
   * Stores trash module settings.
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request, ModuleTrashService $service)
  {

    $data = $request->validate([
      'calendar_link' => 'required|url',
    ]);

    try {

      // try to fetch & update
      $service->sync($data['calendar_link']);

      // save new link
      SettingService::setModuleTrashLink($data['calendar_link']);

    }
    catch (\Throwable $ex)
    {
      $this->handleModuleFailure(self::AUTOTAG, $ex, self::FAILURE_INERTIA);
    }

  }

  /**
   * Collect module informations.
   * @return array
   */
  public static function getHealth(): array
  {

    $lastFetched = SettingService::getModuleTrashLastFetched();
    $latestUpdated = Task::where('autotag', self::AUTOTAG)->max('updated_at');
    $latestDueto = Task::where('autotag', self::AUTOTAG)->max('dueto');

    return [
      'last_fetched' => empty($lastFetched) ? null : $lastFetched->toDateTimeString(),
      'last_updated' => $latestUpdated,
      'uptodate' => $latestDueto,
    ];

  }

  // #####################################################################

  public const AUTOTAG = ModuleTrashService::AUTOTAG;

}
