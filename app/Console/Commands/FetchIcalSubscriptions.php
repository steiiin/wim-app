<?php

namespace App\Console\Commands;

use App\Services\ModuleIcalSubscriptionService;
use App\Services\SettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class FetchIcalSubscriptions extends Command
{
  protected $signature = 'module:ical:fetch';
  protected $description = 'Import events from the Wachenkalender subscriptions';
  public const LOCK = 'module:ical:fetch';

  public function handle(ModuleIcalSubscriptionService $service): int
  {
    $calendars = SettingService::getModuleIcalSubscriptions();
    $lock = Cache::lock(self::LOCK, max(300, count($calendars) * 120 + 300));
    if (!$lock->get()) {
      $this->info('Ein Kalenderabruf läuft bereits.');
      return self::SUCCESS;
    }
    $failed = false;
    try {
      foreach ($calendars as $index => $calendar) {
        if ($index > 0) {
          Sleep::for(15)->seconds();
        }
        try {
          $updated = $service->fetchCalendar($calendar);
          $this->info($calendar['name'].($updated ? ': aktualisiert.' : ': Konfiguration geändert, übersprungen.'));
        } catch (Throwable) {
          $failed = true;
          $message = 'Kalenderabruf fehlgeschlagen (ID '.$calendar['id'].').';
          $this->error($message);
          Log::warning($message);
        }
      }
    } finally {
      $lock->release();
    }
    return $failed ? self::FAILURE : self::SUCCESS;
  }
}
