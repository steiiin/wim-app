<?php

namespace App\Http\Controllers\Modules;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ModuleSharepointService;
use App\Services\SettingService;
use Illuminate\Http\Request;

class SharepointController extends Controller
{

  /**
   * Stores sharepoint module settings.
   * @param \Illuminate\Http\Request $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request, ModuleSharepointService $service)
  {

    $data = $request->validate([
      'branch'          => 'required|in:all,credentials,link',
      'username'        => 'nullable|string|required_if:branch,all,credentials',
      'password'        => 'nullable|string|required_if:branch,all,credentials',
      'secret'          => 'nullable|string|required_if:branch,all,credentials',
      'sharepoint_link' => 'nullable|url|required_if:branch,all,link',
    ]);

    $failed = false;

    try {

      // update with new data
      $service->sync(
        $data['sharepoint_link'] ?? SettingService::getModuleSharepointLink(),
        $data['username'] ?? SettingService::getModuleSharepointUser(),
        $data['password'] ?? SettingService::getModuleSharepointPass(),
        $data['secret'] ?? SettingService::getModuleSharepointSecret(),
      );

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
        SettingService::setModuleSharepointSecret($data['secret']);
        SettingService::setModuleSharepointLink($data['sharepoint_link']);

      }
      else if ($data['branch'] === 'credentials') {

        // store new credentials
        SettingService::setModuleSharepointUser($data['username']);
        SettingService::setModuleSharepointPass($data['password']);
        SettingService::setModuleSharepointSecret($data['secret']);

      }
      else if ($data['branch'] === 'link') {

        // store new link
        SettingService::setModuleSharepointLink($data['sharepoint_link']);

      }

    }
  }

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

  public const AUTOTAG = ModuleSharepointService::AUTOTAG;

}
