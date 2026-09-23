<?php

namespace Tests\Feature;

use App\Exceptions\ServiceFailures\AuthFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use App\Models\Event;
use App\Models\Info;
use App\Models\Task;
use App\Services\ModuleSharepointService;
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
    FakeTrashService::$fetch = FakeSharepointService::$fetch = function () {
      throw new RuntimeException('Unexpected external fetch');
    };
    $this->app->bind(ModuleTrashService::class, FakeTrashService::class);
    $this->app->bind(ModuleSharepointService::class, FakeSharepointService::class);
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    parent::tearDown();
  }

  public static function modules(): array
  {
    return [['trash'], ['sharepoint']];
  }

  private function configure(): void
  {
    SettingService::setModuleTrashLink('https://example.test/trash.ics');
    SettingService::setModuleSharepointLink('https://example.test/list');
    SettingService::setModuleSharepointUser('test-user');
    SettingService::setModuleSharepointPass('test-password');
    SettingService::setModuleSharepointSecret('test-secret');
  }

  private function fakeFetch(string $module, Closure $fetch): void
  {
    if ($module === 'trash') {
      FakeTrashService::$fetch = $fetch;
    } else {
      FakeSharepointService::$fetch = $fetch;
    }
  }

  private function elements(string $module): array
  {
    if ($module === 'trash') {
      return [(object) ['dumpsterName' => 'Restmüll', 'pickupDate' => Carbon::parse('2026-09-24 00:00:00')]];
    }
    return [(object) [
      'title' => 'Prüfung', 'category' => 'RTW', 'meta' => 'Wache', 'description' => 'Material prüfen',
      'start' => Carbon::parse('2026-09-24 08:00:00'), 'until' => Carbon::parse('2026-09-24 09:00:00'),
      'is_allday' => false,
    ]];
  }

  private function seedRecord(string $module, ?string $tag)
  {
    $data = ['payload' => ['title' => 'Existing'], 'autotag' => $tag];
    return $module === 'trash'
      ? Task::create($data + ['dueto' => '2026-09-25 06:00:00'])
      : Event::create($data + ['start' => '2026-09-25 08:00:00', 'until' => '2026-09-25 09:00:00']);
  }

  #[DataProvider('modules')]
  public function test_imports_use_stored_settings_and_replace_only_owned_records(string $module): void
  {
    $this->configure();
    $old = $this->seedRecord($module, $module);
    $manual = $this->seedRecord($module, null);
    $other = $this->seedRecord($module, 'ical-subscription:other');
    $calls = 0;
    $this->fakeFetch($module, function (...$args) use ($module, &$calls) {
      $this->assertSame($module === 'trash' ? ['https://example.test/trash.ics'] : [
        'https://example.test/list', 'test-user', 'test-password', 'test-secret',
      ], $args);
      $calls++;
      return $this->elements($module);
    });

    $this->artisan("module:$module:fetch")->assertExitCode(0);
    $this->artisan("module:$module:fetch")->assertExitCode(0);
    $this->assertSame(2, $calls);
    $this->assertModelMissing($old);
    $this->assertModelExists($manual);
    $this->assertModelExists($other);
    $this->assertDatabaseCount($old->getTable(), 3);

    $record = $old->newQuery()->where('autotag', $module)->sole();
    if ($module === 'trash') {
      $this->assertSame([
        'ab' => 'XXX-Restmüll an die Straße stellen',
        'title' => 'Restmüll an die Straße stellen', 'meta' => 'Abfallkalender',
      ], $record->payload);
      $this->assertSame('2026-09-23 15:00:00', $record->from);
      $this->assertSame('2026-09-24 06:00:00', $record->dueto->toDateTimeString());
    } else {
      $this->assertSame([
        'ab' => 'RTW-Prüfung', 'title' => 'Prüfung', 'vehicle' => 'RTW',
        'meta' => 'Wache', 'description' => 'Material prüfen',
      ], $record->payload);
      $this->assertSame('2026-09-24 08:00:00', $record->start->toDateTimeString());
      $this->assertSame('2026-09-24 09:00:00', $record->until->toDateTimeString());
      $this->assertFalse($record->is_allday);
    }
  }

  #[DataProvider('modules')]
  public function test_unconfigured_modules_skip_without_fetching_or_deleting(string $module): void
  {
    $old = $this->seedRecord($module, $module);
    $this->artisan("module:$module:fetch")
      ->expectsOutputToContain('Abruf übersprungen.')
      ->assertExitCode(0);
    $this->assertModelExists($old);
  }

  public function test_whitespace_trash_link_and_each_missing_sharepoint_setting_skip(): void
  {
    SettingService::setModuleTrashLink('   ');
    $this->artisan('module:trash:fetch')->assertExitCode(0);
    foreach (['Link', 'User', 'Pass', 'Secret'] as $setting) {
      $this->configure();
      SettingService::{'setModuleSharepoint'.$setting}('   ');
      $this->artisan('module:sharepoint:fetch')
        ->expectsOutputToContain('Abruf übersprungen.')
        ->assertExitCode(0);
    }
  }

  public static function failures(): array
  {
    $cases = [];
    foreach (['trash', 'sharepoint'] as $module) {
      foreach ([FetchFailure::class, AuthFailure::class, NothingFoundFailure::class] as $exception) {
        $cases[] = [$module, $exception];
      }
    }
    return $cases;
  }

  #[DataProvider('failures')]
  public function test_fetch_failures_preserve_records_and_do_not_log_secrets(string $module, string $exception): void
  {
    $this->configure();
    $old = $this->seedRecord($module, $module);
    $this->fakeFetch($module, function () use ($exception) {
      throw new $exception('https://test-user:test-password@example.test/?token=test-secret');
    });
    Log::spy();
    $this->artisan("module:$module:fetch")
      ->expectsOutputToContain('konnte nicht aktualisiert werden.')
      ->doesntExpectOutputToContain('test-secret')
      ->doesntExpectOutputToContain('test-password')
      ->assertExitCode(1);
    $this->assertModelExists($old);
    Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($module, $exception) {
      $this->assertStringNotContainsString('test-password', $message);
      $this->assertStringNotContainsString('test-secret', $message);
      $this->assertSame(['module' => $module, 'failure' => $exception], $context);
      return true;
    });
  }

  #[DataProvider('modules')]
  public function test_failed_insert_rolls_back_deletion_and_partial_import(string $module): void
  {
    $this->configure();
    $old = $this->seedRecord($module, $module);
    $this->fakeFetch($module, fn () => array_merge($this->elements($module), $this->elements($module)));
    $model = get_class($old);
    $attempt = 0;
    $model::creating(function () use (&$attempt) {
      if (++$attempt === 2) {
        throw new RuntimeException('Simulated insert failure');
      }
    });
    try {
      $this->artisan("module:$module:fetch")->assertExitCode(1);
      $this->assertSame(2, $attempt);
      $this->assertModelExists($old);
      $this->assertDatabaseCount($old->getTable(), 1);
    } finally {
      $model::flushEventListeners();
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
    foreach (['trash', 'sharepoint'] as $module) {
      $this->postJson("/set-module-$module", [])->assertUnauthorized();
    }
    $this->withSession(['authenticated' => true]);
    $this->postJson('/set-module-trash', ['calendar_link' => 'invalid'])->assertUnprocessable();
    $this->postJson('/set-module-sharepoint', ['branch' => 'all'])->assertUnprocessable();
  }

  public function test_admin_trash_syncs_and_saves_only_on_success(): void
  {
    $this->configure();
    $this->withSession(['authenticated' => true]);
    FakeTrashService::$fetch = function ($link) {
      $this->assertSame('https://example.test/new.ics', $link);
      return $this->elements('trash');
    };
    $this->postJson('/set-module-trash', ['calendar_link' => 'https://example.test/new.ics'])->assertSuccessful();
    $this->assertSame('https://example.test/new.ics', SettingService::getModuleTrashLink());
    $record = Task::sole();
    FakeTrashService::$fetch = function () { throw new FetchFailure('Unavailable'); };
    $this->postJson('/set-module-trash', ['calendar_link' => 'https://example.test/broken.ics'])->assertUnprocessable();
    $this->assertSame('https://example.test/new.ics', SettingService::getModuleTrashLink());
    $this->assertModelExists($record);
  }

  public static function sharepointBranches(): array
  {
    return [['all', false], ['credentials', false], ['link', false], ['all', true], ['credentials', true], ['link', true]];
  }

  #[DataProvider('sharepointBranches')]
  public function test_admin_sharepoint_preserves_branch_save_behavior(string $branch, bool $fails): void
  {
    $this->configure();
    $data = ['branch' => $branch];
    if ($branch !== 'link') {
      $data += ['username' => 'new-user', 'password' => 'new-password', 'secret' => 'new-secret'];
    }
    if ($branch !== 'credentials') {
      $data['sharepoint_link'] = 'https://example.test/new-list';
    }
    FakeSharepointService::$fetch = function (...$args) use ($data, $fails) {
      $this->assertSame([
        $data['sharepoint_link'] ?? 'https://example.test/list',
        $data['username'] ?? 'test-user', $data['password'] ?? 'test-password', $data['secret'] ?? 'test-secret',
      ], $args);
      if ($fails) { throw new AuthFailure('Authentication failed'); }
      return $this->elements('sharepoint');
    };
    $response = $this->withSession(['authenticated' => true])->postJson('/set-module-sharepoint', $data);
    if ($fails) { $response->assertUnprocessable(); } else { $response->assertSuccessful(); }
    $save = !$fails || $branch !== 'all';
    $this->assertSame($save ? ($data['sharepoint_link'] ?? 'https://example.test/list') : 'https://example.test/list', SettingService::getModuleSharepointLink());
    $this->assertSame($save ? ($data['username'] ?? 'test-user') : 'test-user', SettingService::getModuleSharepointUser());
    $this->assertSame($save ? ($data['password'] ?? 'test-password') : 'test-password', SettingService::getModuleSharepointPass());
    $this->assertSame($save ? ($data['secret'] ?? 'test-secret') : 'test-secret', SettingService::getModuleSharepointSecret());
    $this->assertDatabaseCount('events', $fails ? 0 : 1);
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

class FakeSharepointService extends ModuleSharepointService
{
  public static Closure $fetch;

  public static function fetchEvents(string $link, string $username, string $password, string $secret)
  {
    return (self::$fetch)($link, $username, $password, $secret);
  }
}
