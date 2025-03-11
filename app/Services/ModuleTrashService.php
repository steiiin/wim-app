<?php

namespace App\Services;

use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Reader;

class ModuleTrashService
{

  /**
   * Parse the given iCal URL.
   *
   * @param string $url
   * @return array
   *
   * @throws FetchFailure
   *
   */
  public static function fetchElements(string $url)
  {

    SettingService::setModuleTrashLastFetched(Carbon::now());
    $client = new Client();

    try
    {

      // fetch data
      $response = $client->get($url);
      $icalData = (string) $response->getBody();

      // read ical
      $vCalendar = Reader::read($icalData);

      // parse events
      $foundEvents = [];
      foreach ($vCalendar->VEVENT as $event)
      {

        try
        {

          $element = new ModuleTrashElement(
            $event->SUMMARY,
            Carbon::parse($event->DTSTART),
          );

          if (!$element) { continue; }
          $foundEvents[] = $element;

        }
        catch (\Throwable)
        {
          continue;
        }

      }

      if (count($foundEvents) == 0) { throw new NothingFoundFailure(); }
      return $foundEvents;

    }
    catch (GuzzleException $ex)
    {
      throw new FetchFailure('Fehler beim Abruf des Online-Abfallkalenders.');
    }
    catch (ParseException $ex)
    {
      throw new FetchFailure('Fehler beim Auslesen der Abholtermine.');
    }
    catch (\Throwable $ex)
    {
      throw new FetchFailure($ex->getMessage());
    }

  }

}

class ModuleTrashElement
{

  public readonly string $dumpsterName;
  public readonly Carbon $pickupDate;

  public function __construct(string $dumpsterName, Carbon $pickupDate)
  {

    $this->pickupDate = $pickupDate;
    $this->dumpsterName = $dumpsterName;

  }

  public static function get(string $eventSummary, Carbon $eventDate): ?ModuleTrashElement
  {

    $dumpsterName = '';
    if (stripos($eventSummary, 'rest') !== false) { $dumpsterName = 'Restmüllbehälter (Schwarz)'; }
    else if (stripos($eventSummary, 'gelb') !== false) { $dumpsterName = 'Gelben Behälter'; }
    else if (stripos($eventSummary, 'pappe') !== false) { $dumpsterName = 'Papierbehälter (Blau)'; }
    else if (stripos($eventSummary, 'bio') !== false) { $dumpsterName = 'Biobehälter (Braun)'; }
    else {
      return null;
    }

    return new ModuleTrashElement($dumpsterName, $eventDate);

  }

}
