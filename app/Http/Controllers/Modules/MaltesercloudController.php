<?php

namespace App\Http\Controllers\Modules;

use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ModuleMaltesercloudService;
use App\Services\PayloadService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MaltesercloudController extends Controller
{

  /**
   * Stores Maltesercloud module settings.
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {

    $data = $request->validate([
      'username' => 'required|string',
      'password' => 'required|string',
    ]);

    try {

      // try to fetch
      $sharepointEvents = ModuleMaltesercloudService::fetchEvents($data['username'], $data['password']);

      // store new credentials
      SettingService::setModuleMaltesercloudUser($data['username']);
      SettingService::setModuleMaltesercloudPass($data['password']);

      // update entries
      $this->updateEntries($sharepointEvents);

    } catch (FetchFailure) {
      throw ValidationException::withMessages([
        'Zugang' => 'Die Zugangsdaten wurden abgelehnt.'
      ]);
    } catch (NothingFoundFailure) {
      throw ValidationException::withMessages([
        'Kalender' => 'Es wurden keine Sharepoint-Termine gefunden.'
      ]);
    }

  }

  /**
   * API: Updates the entries using the stored module settings.
   * @return \Illuminate\Http\Response
   */
  public function update()
  {

    $username = SettingService::getModuleMaltesercloudUser();
    $password = SettingService::getModuleMaltesercloudPass();
    if (!$username || strlen(trim($username)) === 0 || !$password || strlen(trim($password)) === 0)
    {
      Log::debug('ModuleMaltesercloud: No credentials stored. Canceled Update.');
      die();
    }

    try {

      Log::debug('ModuleMaltesercloud: fetching calendarevents ...');
      $sharepointEvents = ModuleMaltesercloudService::fetchEvents($username, $password);

      Log::debug('ModuleMaltesercloud: recreate auto-events in WIM ...');
      $this->updateEntries($sharepointEvents);

      Log::debug('ModuleMaltesercloud: finished.');

      return response('fetch finished');

    } catch (FetchFailure) {
      Log::alert('ModuleMaltesercloud: error while fetching calendarevents.');
    } catch (NothingFoundFailure) {
      Log::alert('ModuleMaltesercloud: no calendarevents found.');
    }

    return response('fetch failed. See logs.');

  }

  // #####################################################################

  /**
   * Collect module informations.
   * @return array
   */
  public static function getHealth(): array
  {

    $lastFetched = SettingService::getModuleMaltesercloudLastFetched();
    $latestUpdated = Event::where('autotag', self::AUTOTAG)->max('updated_at');
    $latestEvent = Event::where('autotag', self::AUTOTAG)->max('COALESCE(start, until)');

    return [
      'last_fetched' => empty($lastFetched) ? null : $lastFetched->toDateTimeString(),
      'last_updated' => $latestUpdated,
      'uptodate' => $latestEvent,
    ];

  }

  // #####################################################################

  public const AUTOTAG = 'sharepoint';

  // #####################################################################

  /**
   * Purges all tasks created by this module.
   */
  private function purge()
  {
    Event::where('autotag', self::AUTOTAG)->delete();
  }

  // #####################################################################

  /**
   * Fetches all events in the link, removes old auto-added events and create event entries for the new ones.
   * @param array $trashEvents Raw events mapped by ModuleTrashService.
   * @return void
   */
  private function updateEntries(array $sharepointEvents)
  {

    // remove old ones
    $this->purge();

    // create new ones
    DB::transaction(function () use ($sharepointEvents)
    {

      foreach ($sharepointEvents as $trashEvent)
      {

        // TODO: parse raw events

      }
      SettingService::setModuleMaltesercloudLastFetched(Carbon::now());

    });

  }

}
