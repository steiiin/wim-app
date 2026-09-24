<?php

namespace App\Console\Commands;

use App\Services\ModuleTrashService;
use App\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchTrash extends Command
{
  protected $signature = 'module:trash:fetch';
  protected $description = 'Import tasks from the trash calendar';

  public function handle(ModuleTrashService $service): int
  {
    try {
      $link = SettingService::getModuleTrashLink();
      if (trim($link) === '') {
        $this->info('Abfallkalender nicht vollständig konfiguriert. Abruf übersprungen.');
        return self::SUCCESS;
      }

      $this->info('Abfallkalender wird abgerufen ...');
      $service->sync($link);
      $this->info('Abfallkalender aktualisiert.');
      return self::SUCCESS;
    } catch (Throwable $exception) {
      $message = 'Abfallkalender konnte nicht aktualisiert werden.';
      $this->error($message);
      Log::warning($message, ['module' => 'trash', 'failure' => get_class($exception)]);
      return self::FAILURE;
    }
  }
}
