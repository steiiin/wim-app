<?php

namespace Tests\Feature;

use App\Console\Commands\FetchIcalSubscriptions;
use App\Models\Event;
use App\Services\ModuleIcalSubscriptionService as Ical;
use App\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IcalSubscriptionTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutVite();
    Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00', 'Europe/Berlin'));
    Http::preventStrayRequests();
    Sleep::fake();
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    Sleep::fake(false);
    parent::tearDown();
  }

  private function calendar(array $changes = []): array
  {
    return array_replace([
      'id' => (string) Str::uuid(), 'name' => 'Fahrzeuge',
      'url' => 'https://calendar.example/feed.ics', 'icon' => 'mdi-ambulance',
    ], $changes);
  }

  private function store(array $calendars): void
  {
    SettingService::updateModuleIcalSubscriptions(fn () => $calendars);
  }

  private function feed(string $events = ''): string
  {
    return str_replace("\n", "\r\n", "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//WIM Tests//DE\n".$events."END:VCALENDAR\n");
  }

  private function event(string $properties = '', string $uid = 'one'): string
  {
    return "BEGIN:VEVENT\nUID:$uid\nDTSTAMP:20260901T080000Z\nSUMMARY:Prüfung\n".$properties."END:VEVENT\n";
  }

  public function test_configuration_requires_authentication(): void
  {
    $this->postJson('/set-module-ical-subscription', ['calendars' => []])->assertUnauthorized();
    Http::assertNothingSent();
  }

  public function test_save_checks_feeds_normalizes_webcal_and_generates_stable_ids(): void
  {
    Http::fake(['*' => Http::response($this->feed())]);
    $calendar = $this->calendar(['url' => 'webcal://calendar.example/feed.ics']);
    unset($calendar['id'], $calendar['icon']);
    $this->withSession(['authenticated' => true])->post('/set-module-ical-subscription', ['calendars' => [$calendar]])
      ->assertSessionHasNoErrors()->assertRedirect('/admin');
    $saved = SettingService::getModuleIcalSubscriptions()[0];
    $this->assertTrue(Str::isUuid($saved['id']));
    $this->assertSame('https://calendar.example/feed.ics', $saved['url']);
    $this->assertSame('mdi-information', $saved['icon']);
    $this->assertNull($saved['last_success']);
    $this->assertDatabaseCount('events', 0);
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
    $this->get('/admin')->assertOk();
  }

  public function test_invalid_or_unreachable_links_leave_configuration_unchanged(): void
  {
    $prior = $this->calendar();
    $this->store([$prior]);
    Http::fake([
      'calendar.example/*' => Http::response($this->feed()),
      'bad.example/*' => Http::response('<html>Login</html>'),
      'down.example/*' => Http::failedConnection(),
    ]);
    $bad = $this->calendar(['url' => 'https://bad.example/feed']); unset($bad['id']);
    $down = $this->calendar(['url' => 'https://down.example/feed']); unset($down['id']);
    $this->withSession(['authenticated' => true])->postJson('/set-module-ical-subscription', ['calendars' => [$prior, $bad, $down]])
      ->assertUnprocessable()->assertJsonValidationErrors(['calendars.1.url', 'calendars.2.url']);
    $this->assertSame([$prior], SettingService::getModuleIcalSubscriptions());
  }

  public function test_icon_and_url_schemes_are_validated_before_fetching(): void
  {
    $calendar = $this->calendar(['icon' => 'mdi-arbitrary', 'url' => 'file:///etc/passwd']); unset($calendar['id']);
    $this->withSession(['authenticated' => true])->postJson('/set-module-ical-subscription', ['calendars' => [$calendar]])
      ->assertUnprocessable()->assertJsonValidationErrors(['calendars.0.icon', 'calendars.0.url']);
    Http::assertNothingSent();
  }

  public function test_rename_icon_and_deletion_only_affect_owned_events(): void
  {
    $a = $this->calendar(); $b = $this->calendar();
    $this->store([$a, $b]);
    Http::fake(['*' => Http::response($this->feed($this->event("DTSTART:20260924T080000Z\n")))]);
    app(Ical::class)->fetchCalendar($a); app(Ical::class)->fetchCalendar($b);
    Event::create(['payload' => ['title' => 'Manual'], 'start' => now()]);
    $a['name'] = 'Hygiene'; $a['icon'] = 'mdi-hand-wash';
    $this->withSession(['authenticated' => true])->post('/set-module-ical-subscription', ['calendars' => [$a]])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('events', 2);
    $event = Event::where('autotag', Ical::tag($a['id']))->firstOrFail();
    $this->assertSame('Hygiene', $event->payload['vehicle']);
    $this->assertSame('mdi-hand-wash', $event->payload['icon']);
    $this->assertSame('Hygiene-Prüfung', $event->payload['ab']);
    $this->post('/set-module-ical-subscription', ['calendars' => []])->assertSessionHasNoErrors();
    $this->assertSame([], SettingService::getModuleIcalSubscriptions());
    $this->assertDatabaseCount('events', 1);
  }

  public function test_import_is_repeatable_and_empty_feeds_clear_events(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fakeSequence()->push($this->feed($this->event("DTSTART:20260924T080000Z\nLOCATION:Garage\nDESCRIPTION:<b>Check</b>\n")))
      ->push($this->feed($this->event("DTSTART:20260924T090000Z\n")))->push($this->feed());
    $service = app(Ical::class);
    $service->fetchCalendar($calendar);
    $event = Event::firstOrFail();
    $this->assertSame('Garage', $event->payload['meta']);
    $this->assertSame('Check', $event->payload['description']);
    $this->assertSame('10:00', $event->start->format('H:i'));
    $service->fetchCalendar($calendar);
    $this->assertDatabaseCount('events', 1);
    $this->assertSame('11:00', Event::first()->start->format('H:i'));
    $this->getJson('/monitor-poll')->assertOk()->assertJsonPath('events.imminent.0.icon', 'mdi-ambulance')
      ->assertJsonPath('events.imminent.0.vehicle', 'Fahrzeuge')->assertJsonPath('events.imminent.0.type', 'event');
    $service->fetchCalendar($calendar);
    $this->assertDatabaseCount('events', 0);
    $this->assertNotNull(SettingService::getModuleIcalSubscriptions()[0]['last_success']);
  }

  public function test_failure_preserves_events_and_redacts_url_from_error(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fakeSequence()->push($this->feed($this->event("DTSTART:20260924T080000Z\n")))->push('Unavailable', 503)->push('malformed')->push($this->feed());
    $service = app(Ical::class); $service->fetchCalendar($calendar);
    $original = Event::first()->id;
    foreach ([1, 2] as $attempt) {
      try { $service->fetchCalendar($calendar); $this->fail('Expected fetch failure'); }
      catch (RuntimeException $exception) { $this->assertStringNotContainsString($calendar['url'], $exception->getMessage()); }
      $this->assertSame($original, Event::first()->id);
      $status = SettingService::getModuleIcalSubscriptions()[0];
      $this->assertNotNull($status['last_attempt']);
      $this->assertNotNull($status['last_success']);
      $this->assertNotNull($status['last_error']);
    }
    $service->fetchCalendar($calendar);
    $this->assertNull(SettingService::getModuleIcalSubscriptions()[0]['last_error']);
    $this->assertDatabaseCount('events', 0);
  }

  public function test_changed_or_removed_subscription_cannot_commit_inflight_results(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fake(function () use ($calendar) {
      $this->store([array_replace($calendar, ['url' => 'https://changed.example/feed'])]);
      return Http::response($this->feed($this->event("DTSTART:20260924T080000Z\n")));
    });
    $this->assertFalse(app(Ical::class)->fetchCalendar($calendar));
    $this->assertDatabaseCount('events', 0);
    $this->store([]);
    $this->assertFalse(app(Ical::class)->fetchCalendar($calendar));
    Http::assertSentCount(1);
  }

  public function test_deletion_during_fetch_does_not_restore_events(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fake(function () {
      $this->store([]);
      return Http::response($this->feed($this->event("DTSTART:20260924T080000Z\n")));
    });
    $this->assertFalse(app(Ical::class)->fetchCalendar($calendar));
    $this->assertDatabaseCount('events', 0);
    $this->assertSame([], SettingService::getModuleIcalSubscriptions());
  }

  public function test_database_failure_rolls_back_event_replacement(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fake(['*' => Http::response($this->feed($this->event("DTSTART:20260924T080000Z\n")))]);
    app(Ical::class)->fetchCalendar($calendar);
    $original = Event::first()->id;
    Event::creating(function () { throw new RuntimeException('Simulated database failure'); });
    try {
      app(Ical::class)->fetchCalendar($calendar);
      $this->fail('Expected database failure');
    } catch (RuntimeException) {
      $this->assertDatabaseCount('events', 1);
      $this->assertSame($original, Event::first()->id);
    } finally {
      Event::flushEventListeners();
    }
  }

  public function test_configuration_rejects_unknown_ids_and_nonlist_input(): void
  {
    $calendar = $this->calendar();
    $this->withSession(['authenticated' => true])->postJson('/set-module-ical-subscription', ['calendars' => [$calendar]])
      ->assertUnprocessable()->assertJsonValidationErrors('calendars.0.id');
    unset($calendar['id']);
    $this->postJson('/set-module-ical-subscription', ['calendars' => ['named' => $calendar]])
      ->assertUnprocessable()->assertJsonValidationErrors('calendars');
    Http::assertNothingSent();
  }

  public function test_successful_command_does_not_sleep_for_one_calendar(): void
  {
    $this->store([$this->calendar()]);
    Http::fake(['*' => Http::response($this->feed())]);
    $this->artisan('module:ical-subscription:fetch')->assertSuccessful();
    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
  }

  public function test_renaming_during_fetch_uses_latest_name_and_icon(): void
  {
    $calendar = $this->calendar(); $this->store([$calendar]);
    Http::fake(function () use ($calendar) {
      $this->store([array_replace($calendar, ['name' => 'MPG', 'icon' => 'mdi-medical-bag'])]);
      return Http::response($this->feed($this->event("DTSTART:20260924T080000Z\n")));
    });
    app(Ical::class)->fetchCalendar($calendar);
    $this->assertSame('MPG', Event::first()->payload['vehicle']);
    $this->assertSame('mdi-medical-bag', Event::first()->payload['icon']);
  }

  public function test_all_day_duration_floating_times_and_missing_end_dates(): void
  {
    $events = app(Ical::class)->parseEvents($this->feed(
      $this->event("DTSTART;VALUE=DATE:20260924\nDTEND;VALUE=DATE:20260927\n", 'multi').
      $this->event("DTSTART;VALUE=DATE:20260925\n", 'single').
      $this->event("DTSTART:20260924T100000\nDURATION:PT2H\n", 'duration').
      $this->event("DTSTART:20260924T120000\n", 'open')
    ));
    $this->assertCount(4, $events);
    $this->assertTrue($events[0]['is_allday']);
    $this->assertSame('2026-09-26 23:59:59', $events[0]['until']->toDateTimeString());
    $this->assertSame('2026-09-25 23:59:59', $events[1]['until']->toDateTimeString());
    $this->assertSame('10:00', $events[2]['start']->format('H:i'));
    $this->assertSame('12:00', $events[2]['until']->format('H:i'));
    $this->assertNull($events[3]['until']);
  }

  public function test_recurrence_exclusions_overrides_cancellation_and_dst(): void
  {
    $events = app(Ical::class)->parseEvents($this->feed(
      $this->event("DTSTART;TZID=Europe/Berlin:20261023T100000\nDTEND;TZID=Europe/Berlin:20261023T110000\nRRULE:FREQ=DAILY;COUNT=5\nEXDATE;TZID=Europe/Berlin:20261024T100000\n", 'series').
      $this->event("RECURRENCE-ID;TZID=Europe/Berlin:20261025T100000\nDTSTART;TZID=Europe/Berlin:20261025T130000\nDTEND;TZID=Europe/Berlin:20261025T140000\n", 'series').
      $this->event("RECURRENCE-ID;TZID=Europe/Berlin:20261026T100000\nDTSTART;TZID=Europe/Berlin:20261026T100000\nSTATUS:CANCELLED\n", 'series').
      $this->event("DTSTART:20261027T080000Z\nSTATUS:CANCELLED\n", 'cancelled')
    ));
    $this->assertCount(3, $events);
    $starts = collect($events)->pluck('start')->map(fn ($date) => $date->format('Y-m-d H:i P'))->sort()->values()->all();
    $this->assertSame(['2026-10-23 10:00 +02:00', '2026-10-25 13:00 +01:00', '2026-10-27 10:00 +01:00'], $starts);
  }

  public function test_import_window_includes_ongoing_and_day_90_but_excludes_day_91(): void
  {
    $last = Carbon::today()->addDays(90)->format('Ymd');
    $outside = Carbon::today()->addDays(91)->format('Ymd');
    $events = app(Ical::class)->parseEvents($this->feed(
      $this->event("DTSTART:20260901T100000\nDTEND:20260924T100000\n", 'ongoing').
      $this->event("DTSTART:".$last."T230000\n", 'last').
      $this->event("DTSTART:".$outside."T000000\n", 'outside').
      $this->event("DTSTART:20260920T100000\nDTEND:20260921T100000\n", 'past')
    ));
    $this->assertCount(2, $events);
  }

  public function test_command_continues_after_failure_and_waits_only_between_attempts(): void
  {
    $calendars = [$this->calendar(), $this->calendar(), $this->calendar()]; $this->store($calendars);
    $attempt = 0;
    Http::fake(function () use (&$attempt) {
      Sleep::assertSleptTimes($attempt);
      $attempt++;
      return $attempt === 2 ? Http::response('Error', 500) : Http::response($this->feed());
    });
    $this->artisan('module:ical-subscription:fetch')->assertExitCode(1);
    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(60)->seconds(), Sleep::for(60)->seconds()]);
    $this->assertNotNull(SettingService::getModuleIcalSubscriptions()[2]['last_success']);
    $lock = Cache::lock(FetchIcalSubscriptions::LOCK, 60);
    $this->assertTrue($lock->get()); $lock->release();
  }

  public function test_command_skips_overlapping_runs_and_empty_configuration(): void
  {
    $lock = Cache::lock(FetchIcalSubscriptions::LOCK, 60); $lock->get();
    $this->artisan('module:ical-subscription:fetch')->assertSuccessful();
    $lock->release();
    $this->artisan('module:ical-subscription:fetch')->assertSuccessful();
    Http::assertNothingSent(); Sleep::assertNeverSlept();
  }
}
