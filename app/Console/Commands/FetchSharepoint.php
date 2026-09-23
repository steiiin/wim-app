<?php

namespace App\Console\Commands;

use App\Services\ModuleSharepointService;
use App\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchSharepoint extends Command
{
  protected $signature = 'module:sharepoint:fetch';
  protected $description = 'Import events from the SharePoint calendar';

  public function handle(ModuleSharepointService $service): int
  {
    try {
      $link = SettingService::getModuleSharepointLink();
      $username = SettingService::getModuleSharepointUser();
      $password = SettingService::getModuleSharepointPass();
      $secret = SettingService::getModuleSharepointSecret();
      if (trim($link) === '' || trim($username) === '' || trim($password) === '' || trim($secret) === '') {
        $this->info('SharePoint-Kalender nicht vollständig konfiguriert. Abruf übersprungen.');
        return self::SUCCESS;
      }

      $this->info('SharePoint-Kalender wird abgerufen ...');
      $service->sync($link, $username, $password, $secret);
      $this->info('SharePoint-Kalender aktualisiert.');
      return self::SUCCESS;
    } catch (Throwable $exception) {
      $message = 'SharePoint-Kalender konnte nicht aktualisiert werden.';
      $this->error($message);
      Log::warning($message, ['module' => 'sharepoint', 'failure' => get_class($exception)]);
      return self::FAILURE;
    }
  }
}
