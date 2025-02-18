<?php

namespace App\Services;

use App\Exceptions\ModuleTrashService\LinkFailure;
use App\Exceptions\ModuleTrashService\NothingFoundFailure;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
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
   * @throws LinkFailure
   */
  public static function fetchEvents(string $url)
  {

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

          // set timing
          $date = Carbon::parse($event->DTSTART);

          // set title
          $dumpster = '';
          if (stripos($event->SUMMARY, 'rest') !== false) { $dumpster = 'Restmüllbehälter (Schwarz)'; }
          else if (stripos($event->SUMMARY, 'gelb') !== false) { $dumpster = 'Gelben Behälter'; }
          else if (stripos($event->SUMMARY, 'pappe') !== false) { $dumpster = 'Papierbehälter (Blau)'; }
          else if (stripos($event->SUMMARY, 'bio') !== false) { $dumpster = 'Biobehälter (Braun)'; }
          else {
            continue;
          }

          $foundEvents[] = [
            'dumpster' => $dumpster,
            'date' => $date,
          ];

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
      throw new LinkFailure();
    }
    catch (ParseException $ex)
    {
      throw new LinkFailure();
    }
    catch (\Throwable $ex)
    {
      throw new LinkFailure();
    }

  }

}
