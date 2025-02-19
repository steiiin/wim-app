<?php

namespace App\Services;

use App\Exceptions\ServiceFailures\NothingFoundFailure;

class ModuleMaltesercloudService
{

  /**
   * Authenticate and fetch sharepoint calendar events.
   *
   * @param string $username
   * @param string $password
   * @return array
   */
  public static function fetchEvents(string $username, string $password)
  {
    throw new NothingFoundFailure();
  }

}
