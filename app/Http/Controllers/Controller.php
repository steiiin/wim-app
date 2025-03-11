<?php

namespace App\Http\Controllers;

use App\Exceptions\ServiceFailure;
use App\Exceptions\ServiceFailures\AuthFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Controller
{

  public const FAILURE_LOG = 2;
  public const FAILURE_INERTIA = 4;

  protected function handleModuleFailure(string $moduleName, \Throwable $ex, int $howToHandle)
  {

    $failure = '[General]';
    if ($ex instanceof AuthFailure) {
      $failure = '[AuthFailure]';
    } else if ($ex instanceof FetchFailure) {
      $failure = '[FetchFailure]';
    } else if ($ex instanceof NothingFoundFailure) {
      $failure = '[NothingFoundFailure]';
    }
    $failure = $failure.' - '.$ex->getMessage();

    $logMessage = $this->createLogMessage($moduleName, $failure);

    if ($howToHandle == self::FAILURE_LOG) {
      Log::alert($logMessage);
      return response($logMessage);
    }
    else if ($howToHandle == self::FAILURE_INERTIA) {
      throw ValidationException::withMessages([
        'Fehler' => $logMessage
      ]);
    }

  }

  protected function handleLog(string $moduleName, string $message)
  {
    $logMessage = $this->createLogMessage($moduleName, $message);

    Log::debug($logMessage);
    return response($logMessage);
  }

  private function createLogMessage(string $moduleName, string $message)
  {
    return 'module-'.$moduleName.': '.$message.'.';
  }

}
