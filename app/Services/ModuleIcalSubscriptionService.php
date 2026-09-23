<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;
use RuntimeException;
use Throwable;

class ModuleIcalSubscriptionService
{
  public const ICONS = ['mdi-ambulance', 'mdi-medical-bag', 'mdi-hand-wash', 'mdi-information'];
  public const DEFAULT_ICON = 'mdi-information';

  public static function tag(string $id): string
  {
    return 'ical:'.$id;
  }

  private function requestOptions(): array
  {
    return ['allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]];
  }

  public function checkLinks(array $calendars): void
  {
    $responses = Http::pool(function (Pool $pool) use ($calendars) {
      foreach ($calendars as $index => $calendar) {
        $pool->as((string) $index)->connectTimeout(3)->timeout(5)
          ->withOptions($this->requestOptions())->get($calendar['url']);
      }
    });

    $errors = [];
    foreach ($calendars as $index => $calendar) {
      try {
        $response = $responses[$index] ?? null;
        if (!$response instanceof Response || !$response->successful()) {
          throw new RuntimeException();
        }
        $this->readCalendar($response->body());
      } catch (Throwable) {
        $errors["calendars.$index.url"] = 'Der Link ist nicht erreichbar oder enthält keinen gültigen iCalendar.';
      }
    }
    if ($errors) {
      throw ValidationException::withMessages($errors);
    }
  }

  private function readCalendar(string $body): VCalendar
  {
    $calendar = Reader::read($body);
    if (!$calendar instanceof VCalendar) {
      throw new RuntimeException('Ungültiger iCalendar.');
    }
    return $calendar;
  }

  public function parseEvents(string $body): array
  {
    $timezone = new DateTimeZone(config('app.timezone'));
    $from = Carbon::yesterday($timezone)->startOfDay()->toDateTimeImmutable();
    $to = Carbon::today($timezone)->addDays(91)->toDateTimeImmutable();
    $calendar = $this->readCalendar($body)->expand($from, $to, $timezone);
    $events = [];
    foreach ($calendar->select('VEVENT') as $event) {
      if (strtoupper((string) $event->STATUS) === 'CANCELLED') {
        continue;
      }
      if (!isset($event->DTSTART)) {
        throw new RuntimeException('Termin ohne Startdatum.');
      }
      $allDay = !$event->DTSTART->hasTime();
      $start = Carbon::instance($event->DTSTART->getDateTime($timezone))->setTimezone($timezone);
      $end = null;
      if (isset($event->DTEND)) {
        $end = Carbon::instance($event->DTEND->getDateTime($timezone))->setTimezone($timezone);
      } elseif (isset($event->DURATION)) {
        $end = $start->copy()->add(DateTimeParser::parseDuration((string) $event->DURATION));
      } elseif ($allDay) {
        $end = $start->copy()->addDay();
      }
      if ($end && ($end->lt($start) || ($allDay && $end->eq($start)))) {
        throw new RuntimeException('Termin mit ungültigem Enddatum.');
      }
      if ($allDay && $end) {
        $end->subSecond();
      }
      $events[] = [
        'payload' => [
          'title' => trim((string) $event->SUMMARY) ?: 'Ohne Titel',
          'meta' => trim((string) $event->LOCATION) ?: null,
          'description' => trim(html_entity_decode(strip_tags((string) $event->DESCRIPTION), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: null,
        ],
        'start' => $start,
        'until' => $end,
        'is_allday' => $allDay,
      ];
    }
    return $events;
  }

  // Returns false when a subscription was changed or removed during the fetch.
  public function fetchCalendar(array $subscription): bool
  {
    $attempt = now()->toDateTimeString();
    $current = false;
    SettingService::updateModuleIcalSubscriptions(function ($calendars) use ($subscription, $attempt, &$current) {
      foreach ($calendars as &$calendar) {
        if ($calendar['id'] === $subscription['id'] && $calendar['url'] === $subscription['url']) {
          $calendar['last_attempt'] = $attempt;
          $current = true;
        }
      }
      return $calendars;
    });
    if (!$current) {
      return false;
    }

    try {
      $response = Http::connectTimeout(5)->timeout(20)->withOptions($this->requestOptions())->get($subscription['url']);
      if (!$response->successful()) {
        throw new RuntimeException();
      }
      $events = $this->parseEvents($response->body());
      $committed = false;
      SettingService::updateModuleIcalSubscriptions(function ($calendars) use ($subscription, $events, &$committed) {
        foreach ($calendars as &$calendar) {
          if ($calendar['id'] !== $subscription['id'] || $calendar['url'] !== $subscription['url']) {
            continue;
          }
          Event::where('autotag', self::tag($calendar['id']))->delete();
          foreach ($events as $event) {
            $event['payload']['vehicle'] = $calendar['name'];
            $event['payload']['icon'] = $calendar['icon'];
            PayloadService::normalize($event['payload']);
            Event::create($event + ['autotag' => self::tag($calendar['id'])]);
          }
          $calendar['last_success'] = now()->toDateTimeString();
          $calendar['last_error'] = null;
          $committed = true;
        }
        return $calendars;
      });
      return $committed;
    } catch (Throwable) {
      // HTTP exception messages can contain secret subscription URLs.
      $message = 'Kalender konnte nicht abgerufen oder verarbeitet werden.';
      SettingService::updateModuleIcalSubscriptions(function ($calendars) use ($subscription, $message) {
        foreach ($calendars as &$calendar) {
          if ($calendar['id'] === $subscription['id'] && $calendar['url'] === $subscription['url']) {
            $calendar['last_error'] = $message;
          }
        }
        return $calendars;
      });
      throw new RuntimeException($message);
    }
  }
}
