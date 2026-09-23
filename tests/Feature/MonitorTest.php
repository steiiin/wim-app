<?php

namespace Tests\Feature;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00', 'Europe/Berlin'));
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    parent::tearDown();
  }

  private function event(string $title, string $start, ?string $until = null, bool $allDay = false): void
  {
    Event::create([
      'payload' => ['title' => $title],
      'start' => $start,
      'until' => $until,
      'is_allday' => $allDay,
    ]);
  }

  public function test_monitor_includes_ongoing_and_later_today_events_without_duplicating_buckets(): void
  {
    $this->event('Ongoing', '2026-09-22 10:00', '2026-09-24 10:00');
    $this->event('Today with end', '2026-09-23 15:00', '2026-09-23 16:00');
    $this->event('Today without end', '2026-09-23 17:00');
    $this->event('Starting now', '2026-09-23 12:00', '2026-09-23 13:00');
    $this->event('Ending now', '2026-09-23 11:00', '2026-09-23 12:00');
    $this->event('Tomorrow', '2026-09-24 00:00', '2026-09-24 01:00');
    $this->event('Tomorrow without end', '2026-09-24 23:59:59');
    $this->event('Later', '2026-09-25 00:00', '2026-09-25 01:00');
    $this->event('Expired', '2026-09-23 09:00', '2026-09-23 10:00');
    $this->event('Past without end', '2026-09-23 11:00');

    $response = $this->getJson('/monitor-poll')->assertOk();
    $this->assertEqualsCanonicalizing(
      ['Ongoing', 'Today with end', 'Today without end', 'Starting now', 'Ending now'],
      array_column($response->json('events.active'), 'title'),
    );
    $this->assertSame(['Tomorrow', 'Tomorrow without end'], array_column($response->json('events.imminent'), 'title'));
    $this->assertSame(['Later'], array_column($response->json('events.upcoming'), 'title'));
    $response->assertJsonStructure([
      'infos', 'tasks', 'recurring', 'processed', 'lastupdated',
      'events' => ['active' => [['type', 'time_start', 'time_end', 'is_allday']], 'imminent', 'upcoming'],
    ]);
  }

  public function test_monitor_preserves_all_day_and_no_end_expiration_rules(): void
  {
    $this->event('All day', '2026-09-23 00:00', '2026-09-23 23:59:59', true);
    $this->event('All day without end', '2026-09-23 00:00', null, true);
    $this->event('Yesterday all day', '2026-09-22 00:00', null, true);
    $this->event('Tomorrow all day', '2026-09-24 00:00', null, true);
    $this->event('Timed without end', '2026-09-23 15:00');

    $response = $this->getJson('/monitor-poll')->assertOk();
    $this->assertEqualsCanonicalizing(
      ['All day', 'All day without end', 'Timed without end'],
      array_column($response->json('events.active'), 'title'),
    );
    $this->assertSame(['Tomorrow all day'], array_column($response->json('events.imminent'), 'title'));

    Carbon::setTestNow(Carbon::parse('2026-09-23 16:00:00', 'Europe/Berlin'));
    $response = $this->getJson('/monitor-poll')->assertOk();
    $this->assertEqualsCanonicalizing(['All day', 'All day without end'], array_column($response->json('events.active'), 'title'));

    Carbon::setTestNow(Carbon::parse('2026-09-24 00:00:00', 'Europe/Berlin'));
    $response = $this->getJson('/monitor-poll')->assertOk();
    $this->assertSame(['Tomorrow all day'], array_column($response->json('events.active'), 'title'));
    $response->assertJsonCount(0, 'events.imminent');
  }
}
