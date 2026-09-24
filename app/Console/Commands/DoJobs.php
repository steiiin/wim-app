<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Info;
use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DoJobs extends Command
{
  protected $signature = 'app:do-jobs';
  protected $description = 'Delete outdated infos, events, and tasks';

  public function handle(): int
  {
    $this->info('Veraltete Einträge werden gelöscht ...');
    try {
      Info::outdated()->delete();
      Event::outdated()->delete();
      Task::outdated()->delete();
      $this->info('Hintergrundaufgaben ausgeführt.');
      return self::SUCCESS;
    } catch (Throwable $exception) {
      $message = 'Hintergrundaufgaben fehlgeschlagen.';
      $this->error($message);
      Log::warning($message, ['module' => 'do-jobs', 'failure' => get_class($exception)]);
      return self::FAILURE;
    }
  }
}
