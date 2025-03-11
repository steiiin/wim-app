<?php

namespace App\Http\Controllers\Modules;

use App\Exceptions\ServiceFailures\AuthFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ModuleSharepointService;
use App\Services\PayloadService;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SharepointController extends Controller
{

  /**
   * Stores sharepoint module settings.
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {

    $data = $request->validate([
      'branch'         => 'required|in:all,credentials,link',
      'username'       => 'nullable|string|required_if:branch,all,credentials',
      'password'       => 'nullable|string|required_if:branch,all,credentials',
      'sharepoint_link' => 'nullable|url|required_if:branch,all,link',
    ]);

    $failed = false;

    try {

      // update with new data
      $sharepointElements = ModuleSharepointService::fetchEvents(
        $data['username'] ?? SettingService::getModuleSharepointUser(),
        $data['password'] ?? SettingService::getModuleSharepointPass(),
        $data['sharepoint_link'] ?? SettingService::getModuleSharepointLink()
      );
      $this->updateEntries($sharepointElements);

    }
    catch (\Throwable $ex)
    {
      $failed = true;
      $this->handleModuleFailure(self::AUTOTAG, $ex, self::FAILURE_INERTIA);
    }
    finally
    {

      // store new settings

      if ($data['branch'] === 'all' && !$failed) {

        // store all settings
        SettingService::setModuleSharepointUser($data['username']);
        SettingService::setModuleSharepointPass($data['password']);
        SettingService::setModuleSharepointLink($data['sharepoint_link']);

      }
      else if ($data['branch'] === 'credentials') {

        // store new credentials
        SettingService::setModuleSharepointUser($data['username']);
        SettingService::setModuleSharepointPass($data['password']);

      }
      else if ($data['branch'] === 'link') {

        // store new link
        SettingService::setModuleSharepointLink($data['sharepoint_link']);

      }

    }
  }

  /**
   * API: Updates the entries using the stored module settings.
   * @return \Illuminate\Http\Response
   */
  public function update()
  {

    $username = SettingService::getModuleSharepointUser();
    $password = SettingService::getModuleSharepointPass();
    $link = SettingService::getModuleSharepointLink();

    if (!$username || strlen(trim($username)) === 0 || !$password || strlen(trim($password)) === 0 || !$link || strlen(trim($link)) === 0) {
      return $this->handleLog(self::AUTOTAG, 'No credentials/link stored. Canceled Update.');
    }

    try
    {

      $this->handleLog(self::AUTOTAG, 'fetching calendarevents');
      $sharepointElements = ModuleSharepointService::fetchEvents($username, $password, $link);

      $this->handleLog(self::AUTOTAG, 'recreate auto-events in WIM');
      $this->updateEntries($sharepointElements);

      return $this->handleLog(self::AUTOTAG, 'fetch finished');

    }
    catch(\Throwable $ex)
    {
      return $this->handleModuleFailure(self::AUTOTAG, $ex, self::FAILURE_LOG);
    }

  }

  // #####################################################################

  /**
   * Collect module informations.
   * @return array
   */
  public static function getHealth(): array
  {

    $lastFetched = SettingService::getModuleSharepointLastFetched();
    $latestUpdated = Event::where('autotag', self::AUTOTAG)->max('updated_at');
    $latestEvent = Event::where('autotag', self::AUTOTAG)->max('until');

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
  private function updateEntries(array $sharepointElements)
  {

    // remove old ones
    $this->purge();

    // create new ones
    DB::transaction(function () use ($sharepointElements) {

      foreach ($sharepointElements as $element)
      {

        $payload = [
          'title' => $element->title,
          'vehicle' => $element->category,
          'meta' => $element->meta,
          'description' => $element->description,
        ];
        PayloadService::normalize($payload);

        Event::create([
          'payload' => $payload,
          'start' => $element->start,
          'until' => $element->until,
          'is_allday' => $element->is_allday,
          'autotag' => self::AUTOTAG,
        ]);

      }

    });
  }
}
