<?php

namespace App\Http\Controllers\Modules;

use App\Exceptions\ServiceFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\ModuleTrashElement;
use App\Services\ModuleTrashService;
use App\Services\PayloadService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrashController extends Controller
{

  /**
   * Stores trash module settings.
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {

    $data = $request->validate([
      'calendar_link' => 'required|url',
    ]);

    try {

      // try to fetch & update
      $trashElements = ModuleTrashService::fetchElements($data['calendar_link']);
      $this->updateEntries($trashElements);

      // save new link
      SettingService::setModuleTrashLink($data['calendar_link']);

    }
    catch (\Throwable $ex)
    {
      $this->handleModuleFailure(self::AUTOTAG, $ex, self::FAILURE_INERTIA);
    }

  }

  /**
   * API: Updates the entries using the stored module settings.
   * @return \Illuminate\Http\Response
   */
  public function update()
  {

    $calendar_link = SettingService::getModuleTrashLink();
    if (!$calendar_link || strlen(trim($calendar_link)) === 0)
    {
      return $this->handleLog(self::AUTOTAG, 'No trash calendar link stored. Canceled Update.');
    }

    try {

      $this->handleLog(self::AUTOTAG, 'fetching calendarevents ...');
      $trashElements = ModuleTrashService::fetchElements($calendar_link);

      $this->handleLog(self::AUTOTAG, 'recreate auto-events in WIM ...');
      $this->updateEntries($trashElements);

      return $this->handleLog(self::AUTOTAG, 'fetch finished');

    }
    catch (\Throwable $ex)
    {
      $this->handleModuleFailure(self::AUTOTAG, $ex, self::FAILURE_LOG);
      return response('fetch failed. See logs.');
    }

  }

  // #####################################################################

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

  public const AUTOTAG = 'trash';

  // #####################################################################

  /**
   * Fetches all events in the link, removes old auto-added events and create event entries for the new ones.
   * @param array $trashElements Raw events mapped by ModuleTrashService.
   * @return void
   */
  private function updateEntries(array $trashElements)
  {

    // remove old ones
    Task::where('autotag', self::AUTOTAG)->delete();

    // create new ones
    DB::transaction(function () use ($trashElements)
    {

      foreach ($trashElements as $trashElement)
      {

        $payload = [
          'title' => $trashElement->dumpsterName . ' an die Straße stellen',
          'meta' => 'Abfallkalender'
        ];
        PayloadService::normalize($payload);

        Task::create([
          'payload' => $payload,
          'from' => $trashElement->pickupDate->copy()->addHours(-9),
          'dueto' => $trashElement->pickupDate->copy()->addHours(6),
          'autotag' => self::AUTOTAG,
        ]);

      }

    });

  }

}
