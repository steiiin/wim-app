<?php

namespace Tests\Feature;

use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use App\Models\Event;
use App\Models\Info;
use App\Models\Task;
use App\Services\ModuleTrashService;
use App\Services\SettingService;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BackgroundCommandsTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutVite();
    Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00', 'Europe/Berlin'));
    // Replace only the external fetch boundary, keeping real import persistence.
    FakeTrashService::$fetch = function () {
      throw new RuntimeException('Unexpected external fetch');
    };
    $this->app->bind(ModuleTrashService::class, FakeTrashService::class);
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    parent::tearDown();
  }

  private function configure(): void
  {
    SettingService::setModuleTrashLink('https://example.test/trash.ics');
  }

  private function elements(): array
  {
    return [(object) ['dumpsterName' => 'Restmüll', 'pickupDate' => Carbon::parse('2026-09-24 00:00:00')]];
  }

  private function seedRecord(?string $tag): Task
  {
    return Task::create([
      'payload' => ['title' => 'Existing'], 'autotag' => $tag, 'dueto' => '2026-09-25 06:00:00',
    ]);
  }

  public function test_imports_use_stored_settings_and_replace_only_owned_records(): void
  {
    $this->configure();
    $old = $this->seedRecord('trash');
    $manual = $this->seedRecord(null);
    $other = $this->seedRecord('other-module');
    $calls = 0;
    FakeTrashService::$fetch = function ($link) use (&$calls) {
      $this->assertSame('https://example.test/trash.ics', $link);
      $calls++;
      return $this->elements();
    };

    $this->artisan('module:trash:fetch')->assertExitCode(0);
    $this->artisan('module:trash:fetch')->assertExitCode(0);
    $this->assertSame(2, $calls);
    $this->assertModelMissing($old);
    $this->assertModelExists($manual);
    $this->assertModelExists($other);
    $this->assertDatabaseCount('tasks', 3);

    $record = Task::where('autotag', 'trash')->sole();
    $this->assertSame([
      'ab' => 'XXX-Restmüll an die Straße stellen',
      'title' => 'Restmüll an die Straße stellen', 'meta' => 'Abfallkalender',
      'icon' => 'mdi-trash-can',
    ], $record->payload);
    $this->assertSame('2026-09-23 15:00:00', $record->from);
    $this->assertSame('2026-09-24 06:00:00', $record->dueto->toDateTimeString());
  }

  public function test_unconfigured_module_skips_without_fetching_or_deleting(): void
  {
    $old = $this->seedRecord('trash');
    $this->artisan('module:trash:fetch')
      ->expectsOutputToContain('Abruf übersprungen.')
      ->assertExitCode(0);
    $this->assertModelExists($old);
  }

  public function test_whitespace_trash_link_skips(): void
  {
    SettingService::setModuleTrashLink('   ');
    $this->artisan('module:trash:fetch')
      ->expectsOutputToContain('Abruf übersprungen.')
      ->assertExitCode(0);
  }

  public static function failures(): array
  {
    return [[FetchFailure::class], [NothingFoundFailure::class]];
  }

  #[DataProvider('failures')]
  public function test_fetch_failures_preserve_records_and_do_not_log_secrets(string $exception): void
  {
    $this->configure();
    $old = $this->seedRecord('trash');
    FakeTrashService::$fetch = function () use ($exception) {
      throw new $exception('https://test-user:test-password@example.test/?token=test-secret');
    };
    Log::spy();
    $this->artisan('module:trash:fetch')
      ->expectsOutputToContain('konnte nicht aktualisiert werden.')
      ->doesntExpectOutputToContain('test-secret')
      ->doesntExpectOutputToContain('test-password')
      ->assertExitCode(1);
    $this->assertModelExists($old);
    Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($exception) {
      $this->assertStringNotContainsString('test-password', $message);
      $this->assertStringNotContainsString('test-secret', $message);
      $this->assertSame(['module' => 'trash', 'failure' => $exception], $context);
      return true;
    });
  }

  public function test_failed_insert_rolls_back_deletion_and_partial_import(): void
  {
    $this->configure();
    $old = $this->seedRecord('trash');
    FakeTrashService::$fetch = fn () => array_merge($this->elements(), $this->elements());
    $attempt = 0;
    Task::creating(function () use (&$attempt) {
      if (++$attempt === 2) {
        throw new RuntimeException('Simulated insert failure');
      }
    });
    try {
      $this->artisan('module:trash:fetch')->assertExitCode(1);
      $this->assertSame(2, $attempt);
      $this->assertModelExists($old);
      $this->assertDatabaseCount('tasks', 1);
    } finally {
      Task::flushEventListeners();
    }
  }

  public function test_cleanup_preserves_boundary_current_future_and_permanent_records(): void
  {
    // Existing scopes use a strict comparison with yesterday's end, at DB second precision.
    $dates = ['2026-09-22 23:59:58', '2026-09-22 23:59:59', '2026-09-23 12:00:00', '2026-09-24 12:00:00'];
    $removed = [];
    $retained = [];
    foreach ($dates as $index => $date) {
      $records = [
        Info::create(['payload' => [], 'is_permanent' => false, 'until' => $date]),
        Event::create(['payload' => [], 'start' => $date, 'until' => $date]),
        Event::create(['payload' => [], 'start' => $date, 'until' => null]),
        Task::create(['payload' => [], 'dueto' => $date]),
      ];
      if ($index === 0) {
        $removed = $records;
      } else {
        $retained = array_merge($retained, $records);
      }
    }
    $retained[] = Info::create(['payload' => [], 'is_permanent' => true, 'until' => $dates[0]]);
    $retained[] = Info::create(['payload' => [], 'is_permanent' => false, 'until' => null]);
    $this->artisan('app:do-jobs')->assertExitCode(0);
    $this->artisan('app:do-jobs')->assertExitCode(0);
    foreach ($removed as $record) { $this->assertModelMissing($record); }
    foreach ($retained as $record) { $this->assertModelExists($record); }
  }

  public function test_cleanup_database_failure_returns_failure(): void
  {
    Schema::drop('infos');
    Log::spy();
    $this->artisan('app:do-jobs')->expectsOutput('Hintergrundaufgaben fehlgeschlagen.')->assertExitCode(1);
    Log::shouldHaveReceived('warning')->once();
  }

  public function test_background_routes_are_removed_and_client_reporting_remains(): void
  {
    foreach (['trash', 'sharepoint', 'do-jobs'] as $route) {
      $this->getJson("/api/$route")->assertNotFound();
      $this->head("/api/$route")->assertNotFound();
    }
    Log::spy();
    $this->postJson('/api/client-error', ['message' => 'Browser error'])->assertNoContent();
    Log::shouldHaveReceived('error')->once()->with('FRONTEND-ERROR', ['message' => 'Browser error']);
  }

  public function test_admin_module_settings_require_authentication_and_validation(): void
  {
    $this->postJson('/set-module-trash', [])->assertUnauthorized();
    $this->withSession(['authenticated' => true]);
    $this->postJson('/set-module-trash', ['calendar_link' => 'invalid'])->assertUnprocessable();
  }

  public function test_admin_trash_syncs_and_saves_only_on_success(): void
  {
    $this->configure();
    $this->withSession(['authenticated' => true]);
    FakeTrashService::$fetch = function ($link) {
      $this->assertSame('https://example.test/new.ics', $link);
      return $this->elements();
    };
    $this->postJson('/set-module-trash', ['calendar_link' => 'https://example.test/new.ics'])->assertSuccessful();
    $this->assertSame('https://example.test/new.ics', SettingService::getModuleTrashLink());
    $record = Task::sole();
    FakeTrashService::$fetch = function () { throw new FetchFailure('Unavailable'); };
    $this->postJson('/set-module-trash', ['calendar_link' => 'https://example.test/broken.ics'])->assertUnprocessable();
    $this->assertSame('https://example.test/new.ics', SettingService::getModuleTrashLink());
    $this->assertModelExists($record);
  }

}

class FakeTrashService extends ModuleTrashService
{
  public static Closure $fetch;

  public static function fetchElements(string $url)
  {
    return (self::$fetch)($url);
  }
}
