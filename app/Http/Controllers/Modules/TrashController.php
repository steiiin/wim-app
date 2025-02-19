<?php

namespace App\Http\Controllers\Modules;

use App\Exceptions\ModuleTrashService\LinkFailure;
use App\Exceptions\ModuleTrashService\NothingFoundFailure;
use App\Http\Controllers\Controller;
use App\Models\Task;
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

  public function store(Request $request)
  {

    $data = $request->validate([
      'calendar_link' => 'required|url',
    ]);

    try {

      // try to fetch
      $trashEvents = ModuleTrashService::fetchEvents($data['calendar_link']);

      // store new link
      SettingService::setModuleTrashLink($data['calendar_link']);

      // update entries
      $this->updateEntries($trashEvents);

    } catch (LinkFailure) {
      throw ValidationException::withMessages([
        'Link' => 'Der angegebene Link enthielt keinen gültigen Kalender.'
      ]);
    } catch (NothingFoundFailure) {
      throw ValidationException::withMessages([
        'Kalender' => 'Es wurden keine Abfalltermine gefunden.'
      ]);
    }

  }

  public function update()
  {

    $calendar_link = SettingService::getModuleTrashLink();
    if (!$calendar_link || strlen(trim($calendar_link)) === 0)
    {
      Log::debug('ModuleTrash: No trash calendar link stored. Canceled Update.');
      die();
    }

    try {

      Log::debug('ModuleTrash: fetching calendarevents ...');
      $trashEvents = ModuleTrashService::fetchEvents($calendar_link);

      Log::debug('ModuleTrash: recreate auto-events in WIM ...');
      $this->updateEntries($trashEvents);

      Log::debug('ModuleTrash: finished.');

      return response('fetch finished');

    } catch (LinkFailure) {
      Log::alert('ModuleTrash: error while fetching calendarevents.');
    } catch (NothingFoundFailure) {
      Log::alert('ModuleTrash: no calendarevents found.');
    }

    return response('fetch failed. See logs.');

  }

  // #####################################################################

  public static function getHealth(): array
  {

    $lastFetched = SettingService::getModuleTrashLastFetched();
    $latestUpdated = Task::where('autotag', 'trash')->max('updated_at');
    $latestDueto = Task::where('autotag', 'trash')->max('dueto');

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
   * Purge all tasks created by this module.
   *
   */
  private function purge()
  {
    Task::where('autotag', self::AUTOTAG)->delete();
  }

  // #####################################################################

  /**
   * Fetches all events in the link, removes old auto-added events and create event entries for the new ones.
   *
   */
  private function updateEntries(array $trashEvents)
  {

    // remove old ones
    $this->purge();

    // create new ones
    DB::transaction(function () use ($trashEvents)
    {

      foreach ($trashEvents as $trashEvent)
      {

        $payload = [
          'title' => $trashEvent['dumpster'] . ' an die Straße stellen',
          'meta' => 'Abfallkalender'
        ];
        PayloadService::normalize($payload);

        Task::create([
          'payload' => $payload,
          'from' => $trashEvent['date']->copy()->addHours(-9),
          'dueto' => $trashEvent['date']->copy()->addHours(6),
          'autotag' => self::AUTOTAG,
        ]);

      }
      SettingService::setModuleTrashLastFetched(Carbon::now());

    });

  }

}
