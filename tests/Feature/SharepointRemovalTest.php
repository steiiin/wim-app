<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharepointRemovalTest extends TestCase
{
  use RefreshDatabase;

  private function migration(): Migration
  {
    return require database_path('migrations/2026_09_23_000001_remove_sharepoint_module_data.php');
  }

  private function seedSharepointSettings(): void
  {
    foreach (['user', 'pass', 'secret', 'link', 'fetched'] as $suffix) {
      Setting::create(['key' => 'module_sharepoint_'.$suffix, 'value' => 'legacy-value']);
    }
  }

  public function test_removed_interfaces_are_unavailable(): void
  {
    $this->postJson('/set-module-sharepoint', [])->assertNotFound();
    $this->withSession(['authenticated' => true])->postJson('/set-module-sharepoint', [])->assertNotFound();
    $this->assertArrayNotHasKey('module:sharepoint:fetch', Artisan::all());

    $this->withoutVite()->get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
      ->component('Admin')
      ->missing('moduleSharepoint')
      ->has('moduleTrash')
      ->has('moduleIcalSubscription')
    );
  }

  public function test_cleanup_removes_only_sharepoint_data_and_can_be_repeated(): void
  {
    $this->seedSharepointSettings();
    $retainedSettings = [];
    foreach (['station_name', 'module_trash_link', 'module_ical_subscriptions', 'module_sharepoint_custom'] as $key) {
      $retainedSettings[] = Setting::create(['key' => $key, 'value' => 'preserved']);
    }
    $removed = Event::create(['payload' => [], 'start' => '2026-09-24 08:00:00', 'autotag' => 'sharepoint']);
    $retainedEvents = [];
    foreach ([null, 'ical-subscription:calendar-id', 'sharepoint-other'] as $tag) {
      $retainedEvents[] = Event::create(['payload' => [], 'start' => '2026-09-24 08:00:00', 'autotag' => $tag]);
    }

    // Reload database defaults and serialized values before comparing snapshots.
    foreach (array_merge($retainedSettings, $retainedEvents) as $record) {
      $record->refresh();
    }

    $migration = $this->migration();
    $migration->up();
    $migration->up();
    $migration->down();

    $this->assertModelMissing($removed);
    $this->assertDatabaseCount('settings', count($retainedSettings));
    $this->assertDatabaseCount('events', count($retainedEvents));
    foreach (array_merge($retainedSettings, $retainedEvents) as $record) {
      $this->assertSame($record->getAttributes(), $record->fresh()->getAttributes());
    }
  }

  public function test_cleanup_is_safe_on_an_empty_database(): void
  {
    $this->migration()->up();
    $this->assertDatabaseCount('settings', 0);
    $this->assertDatabaseCount('events', 0);
  }

  public function test_cleanup_rolls_back_settings_deletion_if_event_deletion_fails(): void
  {
    $this->seedSharepointSettings();
    $event = Event::create(['payload' => [], 'start' => '2026-09-24 08:00:00', 'autotag' => 'sharepoint']);
    DB::statement("CREATE TRIGGER prevent_event_deletion BEFORE DELETE ON events BEGIN SELECT RAISE(ABORT, 'Simulated delete failure'); END");

    try {
      try {
        $this->migration()->up();
        $this->fail('Expected event deletion to fail.');
      } catch (QueryException $exception) {
        $this->assertStringContainsString('Simulated delete failure', $exception->getMessage());
      }
      $this->assertDatabaseCount('settings', 5);
      $this->assertModelExists($event);
    } finally {
      DB::statement('DROP TRIGGER prevent_event_deletion');
    }
  }
}
