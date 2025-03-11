<?php

namespace App\Services;

use App\Exceptions\ServiceFailures\AuthFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Validation\ValidationException;

class ModuleSharepointService
{

  private static string $username;
  private static string $password;
  private static string $link_list;
  private static string $link_base;

  private static Client $client;

  /**
   * Authenticate and fetch sharepoint calendar events.
   *
   * @param string $username
   * @param string $password
   * @param string $link
   * @return array
   *
   * @throws AuthFailure
   * @throws FetchFailure
   *
   */
  public static function fetchEvents(string $username, string $password, string $link)
  {

    SettingService::setModuleSharepointLastFetched(Carbon::now());

    self::init($username, $password, $link);
    self::login();

    return self::loadListItems();

  }

  // #region reverse-engineered authentification

    private static function init(string $username, string $password, string $link)
    {

      self::$username = $username;
      self::$password = $password;
      self::$link_list = $link;
      self::$link_base = trim(parse_url($link, PHP_URL_SCHEME).'://'.parse_url($link, PHP_URL_HOST), '/');

      $cookieJar = new CookieJar();
      self::$client = new Client([
        'cookies' => $cookieJar,
        'headers' => [
          'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4482.0 Safari/537.36 Edg/92.0.874.0',
          'Accept-Language' => 'de-DE'
        ]
      ]);

    }

    /**
     * @throws AuthFailure
     */
    private static function login()
    {

      try
      {

        $response = self::$client->get(self::$link_base, [
          'headers' => [
            'Accept' => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
          ],
          'allow_redirects' => [
            'track_redirects' => true,
          ],
        ]);

        if ($response->getStatusCode() !== 200) {
          throw new AuthFailure('Die Seite lehnte einen Aufruf ab.');
        }

        if (!$response->hasHeader('X-Guzzle-Redirect-History')) {
          throw new AuthFailure('Die Seite leitete nicht zum Sharepoint-Login weiter.');
        }

        $reponseHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $authUrl = end($reponseHistory);

        if (stripos($authUrl, 'login.microsoftonline.com') === false) {
          throw new AuthFailure('Die Seite nutzt keine Microsoft-Authentifizierung.');
        }

        if (!$response->hasHeader('x-ms-request-id')) {
          throw new AuthFailure('Keine MS-Request-ID gefunden.');
        }

        $msRequestId = $response->getHeader('x-ms-request-id')[0];
        $oauthId = self::extractValue($authUrl, '.com/', '/');

        if ($oauthId === false) {
          throw new AuthFailure('Prop "oauthId" nicht gefunden.');
        }

        $body = $response->getBody()->getContents();
        $flowToken = self::extractValue($body, '"sFT":"', '"');
        $orgRequest = self::extractValue($body, 'fctx%3d', '\u0026');
        $hpgid = self::extractValue($body, '"hpgid":', ',');
        $hpgact = self::extractValue($body, '"hpgact":', ',');
        $paramsCanary = self::extractValue($body, '"apiCanary":"', '"');
        $loginCanary = self::extractValue($body, '"canary":"', '"');
        $clientId = self::extractValue($body, 'client-request-id=', '\u0026');
        $state = self::extractValue($body, '"state":"', '"');

        if ($flowToken === false) {
          throw new AuthFailure('Prop "flowToken" nicht gefunden.');
        }
        if ($orgRequest === false) {
          throw new AuthFailure('Prop "orgRequest" nicht gefunden.');
        }
        if ($hpgid === false) {
          throw new AuthFailure('Prop "hpgid" nicht gefunden.');
        }
        if ($hpgact === false) {
          throw new AuthFailure('Prop "hpgact" nicht gefunden.');
        }
        if ($paramsCanary === false) {
          throw new AuthFailure('Prop "paramsCanary" nicht gefunden.');
        }
        if ($loginCanary === false) {
          throw new AuthFailure('Prop "loginCanary" nicht gefunden.');
        }
        if ($clientId === false) {
          throw new AuthFailure('Prop "clientId" nicht gefunden.');
        }
        if ($state === false) {
          throw new AuthFailure('Prop "state" nicht gefunden.');
        }

        $paramsHeaders = [
          'Accept'            => 'application/json',
          'canary'            => $paramsCanary,
          'client-request-id' => $clientId,
          'hpgact'            => $hpgact,
          'hpgid'             => $hpgid,
          'hprequestid'       => $msRequestId,
          'Priority'          => 'u=0'
        ];
        $paramsBody = [
          "username"                         => self::$username,
          "isOtherIdpSupported"              => true,
          "checkPhones"                      => false,
          "isRemoteNGCSupported"             => true,
          "isCookieBannerShown"              => false,
          "isFidoSupported"                  => true,
          "originalRequest"                  => $orgRequest,
          "country"                          => "DE",
          "forceotclogin"                    => false,
          "isExternalFederationDisallowed"   => false,
          "isRemoteConnectSupported"         => false,
          "federationFlags"                  => 0,
          "isSignup"                         => false,
          "flowToken"                        => $flowToken,
          "isAccessPassSupported"            => true,
          "isQrCodePinSupported"             => true
        ];

        $response = self::$client->post('https://login.microsoftonline.com/common/GetCredentialType?mkt=de', [
          'headers' => $paramsHeaders,
          'json' => $paramsBody,
        ]);

        if ($response->getStatusCode() !== 200) {
          throw new AuthFailure('Die Seite lehnte den angegebenen Benutzernamen ab.');
        }

        $authBody = [
          'i13'               => 0,
          'login'             => self::$username,
          'loginfmt'          => self::$username,
          'type'              => 11,
          'LoginOptions'      => 3,
          'passwd'            => self::$password,
          'ps'                => 2,
          'canary'            => $loginCanary,
          'ctx'               => $orgRequest,
          'hpgrequestid'      => $msRequestId,
          'flowToken'         => $flowToken,
          'NewUser'           => 1,
          'fspost'            => 0,
          'i21'               => 0,
          'CookieDisclosure'  => 0,
          'IsFidoSupported'   => 1,
          'isSignupPost'      => 0,
          'i19'               => 45842
        ];

        $response = self::$client->post('https://login.microsoftonline.com/' . $oauthId . '/login', [
          'form_params' => $authBody,
        ]);

        if ($response->getStatusCode() !== 200) {
          throw new AuthFailure('Die Anmeldung ist fehlgeschlagen.');
        }

        $body = $response->getBody()->getContents();

        if (stripos($body, '/_forms/default.aspx') === false) {
          throw new AuthFailure('Die Weiterleitung nach der Anmeldung schlug fehl.');
        }
        if (!$response->hasHeader('x-ms-request-id')) {
          throw new AuthFailure('Kein Header "x-ms-request-id" gefunden.');
        }

        $msRequestId = $response->getHeader('x-ms-request-id');
        $code = self::extractValue($body, 'name="code" value="', '"');
        $idToken = self::extractValue($body, 'name="id_token" value="', '"');
        $sessionState = self::extractValue($body, 'name="session_state" value="', '"');
        $correlation = self::extractValue($body, 'name="correlation_id" value="', '"');

        if ($code === false) {
          throw new AuthFailure('Prop "code" nicht gefunden.');
        }
        if ($idToken === false) {
          throw new AuthFailure('Prop "idToken" nicht gefunden.');
        }
        if ($sessionState === false) {
          throw new AuthFailure('Prop "sessionState" nicht gefunden.');
        }
        if ($correlation === false) {
          throw new AuthFailure('Prop "correlation" nicht gefunden.');
        }

        $accessBody = [
          'code'            => $code,
          'id_token'        => $idToken,
          'state'           => $state,
          'session_state'   => $sessionState,
          'correlation_id'  => $correlation,
        ];
        $response = self::$client->post(self::$link_base . '/_forms/default.aspx', [
          'form_params' => $accessBody
        ]);

        if ($response->getStatusCode() !== 200) {
          throw new AuthFailure('Die Anmeldung wurde abgelehnt.');
        }

      }
      catch (GuzzleException)
      {
        throw new AuthFailure('Bei der Kommunikation ist ein Fehler aufgetreten.');
      }

    }

    private static function extractValue($body, $key, $endSep)
    {
      $needlePosition = strpos($body, $key);
      if ($needlePosition !== false) {
        $needlePosition += strlen($key);
        $needleEnd = strpos($body, $endSep, $needlePosition);
        return substr($body, $needlePosition, $needleEnd - $needlePosition);
      }
      return false;
    }

  // #endregion

  // #region API-Calls

    /**
     * @throws FetchFailure
     * @throws AuthFailure
     */
    private static function loadListItems(): array
    {

      $filter_start = gmdate("Y-m-d\TH:i:s\Z", strtotime('-1 days'));
      $filter_end = gmdate("Y-m-d\TH:i:s\Z", strtotime('+30 days'));
      $headers = [ 'Accept' => 'application/json; odata=verbose' ];
      $apiUrl = self::$link_list . '/items?$select=ID,GUID,Title,Description,EventDate,EndDate,fAllDayEvent,Category,Location'
        . '&$filter=(EndDate ge datetime\'' . $filter_start . '\') and (EventDate le datetime\'' . $filter_end . '\')'
        . '&$orderby=EventDate%20asc';

      try
      {

        $response = self::$client->get($apiUrl, [ 'headers' => $headers ]);

        if ($response->getStatusCode() !== 200) {
          throw new FetchFailure('Kalenderabruf wurde abgelehnt.');
        }

        $listBody = $response->getBody()->getContents();
        $listJson = json_decode($listBody, true);
        if (!isset($listJson['d']['results'])) { throw new FetchFailure('Sharepoint hat die Liste nicht gefunden.'); }
        $listItems = $listJson['d']['results'];

        $elements = [];
        foreach ($listItems as $item)
        {
          $elements[] = ModuleSharepointElement::get($item);
        }

        if (count($elements) > 0) { return $elements; }
        else { throw new NothingFoundFailure('Keine Ereignisse in der angegeben Sharepoint-Liste.'); }

      }
      catch (GuzzleException)
      {
        throw new FetchFailure('Fehler bei der Kommunikation');
      }

    }

  // #endregion

}

class ModuleSharepointElement
{

  public readonly string $title;
  public readonly ?string $category;
  public readonly ?string $meta;
  public readonly ?string $description;
  public readonly ?Carbon $start;
  public readonly Carbon $until;
  public readonly bool $is_allday;

  public function __construct(string $title, ?string $category, ?string $meta, ?string $description, ?Carbon $start, Carbon $until, bool $is_allday)
  {
    $this->title = $title;
    $this->category = $category;
    $this->meta = $meta;
    $this->description = $description;
    $this->start = $start;
    $this->until = $until;
    $this->is_allday = $is_allday;
  }

  public static function get(array $event): ?ModuleSharepointElement
  {

    $title = $event['Title'];
    $description = isset($event['Description']) ? html_entity_decode(strip_tags($event['Description'])) : null;

    $category = $event['Category'] ?? null;
    $meta = $event['Location'] ?? null;

    if (strpos($event['Title'], 'MDR') !== false && !$category && !$description)
    {
      $title = str_replace('MDR', '', $title);
      $category = 'Monatsdesinfektion';
    }

    $start = self::parseButIgnoreUTC($event['EventDate']);
    $until = self::parseButIgnoreUTC($event['EndDate']);
    $is_allday = $event['fAllDayEvent'];

    return new ModuleSharepointElement($title, $category, $meta, $description, $start, $until, $is_allday);

  }

  private static function parseButIgnoreUTC(?string $datetime): ?Carbon
  {
    if (!$datetime) { return null; }
    $strippedDateString = str_replace('Z', '', $datetime);
    return Carbon::parse($strippedDateString, config('app.timezone'));
  }

}

