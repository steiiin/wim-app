<?php

namespace App\Services;

use App\Exceptions\ServiceFailures\AuthFailure;
use App\Exceptions\ServiceFailures\FetchFailure;
use App\Exceptions\ServiceFailures\NothingFoundFailure;
use Carbon\Carbon;
use OTPHP\TOTP;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;

class ModuleSharepointService
{

  private static string $username;
  private static string $password;
  private static TOTP $totp_sec;
  private static string $link_list;
  private static string $link_base;

  private static Client $client;

  /**
   * Authenticate and fetch sharepoint calendar events.
   *
   * @param string $username
   * @param string $password
   * @param string $secret
   * @param string $link
   * @return array
   *
   * @throws AuthFailure
   * @throws FetchFailure
   *
   */
  public static function fetchEvents(string $link, string $username, string $password, string $secret)
  {

    SettingService::setModuleSharepointLastFetched(Carbon::now());

    self::init($link, $username, $password, $secret);
    self::login();

    return self::loadListItems();

  }

  // #region reverse-engineered authentification

    private static function init(string $link, string $username, string $password, string $totp_sec)
    {

      self::$username = $username;
      self::$password = $password;
      self::$totp_sec = TOTP::create($totp_sec);
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

        /********************************************************************************************
         * 1: try unauthorized > get redirected
         ********************************************************************************************/

        $response = self::$client->get(self::$link_base, [
          'headers' => [
            'Accept' => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
          ],
          'allow_redirects' => [
            'track_redirects' => true,
          ],
        ]);
        self::throwNotOk($response, 'Sharepoint hat Zugriff abgelehnt');

        // get last redirect url
        if (!$response->hasHeader('X-Guzzle-Redirect-History')) {
          throw new AuthFailure('Die Seite leitete nicht zum Sharepoint-Login weiter.');
        }
        $reponseHistory = $response->getHeader('X-Guzzle-Redirect-History');
        $authUrl = end($reponseHistory);

        // check current url
        self::throwIfMissing($authUrl, 'login.microsoftonline.com', 'Die Seite nutzt keine Microsoft-Authentifizierung');

        $msRequestId = $response->getHeaderLine('x-ms-request-id') ?: self::throwAuthPro('x-ms-request-id', 1);
        $oauthId = self::extractValue($authUrl, '.com/', '/') ?: self::throwAuthPro('oauthId', 1);

        $body = $response->getBody()->getContents();

        $flowToken = self::extractValue($body, '"sFT":"', '"') ?: self::throwAuthPro('sFT', 1);
        $orgRequest = self::extractValue($body, 'fctx%3d', '\u0026') ?: self::throwAuthPro('fctx', 1);
        $hpgid = self::extractValue($body, '"hpgid":', ',') ?: self::throwAuthPro('hpgid', 1);
        $hpgact = self::extractValue($body, '"hpgact":', ',') ?: self::throwAuthPro('hpgact', 1);
        $apiCanary = self::extractValue($body, '"apiCanary":"', '"') ?: self::throwAuthPro('apiCanary', 1);
        $loginCanary = self::extractValue($body, '"canary":"', '"') ?: self::throwAuthPro('canary', 1);
        $clientId = self::extractValue($body, 'client-request-id=', '\u0026') ?: self::throwAuthPro('clientId', 1);
        $state = self::extractValue($body, '"state":"', '"') ?: self::throwAuthPro('state', 1);

        /********************************************************************************************
         * 2: get credential type
         ********************************************************************************************/

        $paramsHeaders = [
          'Accept'            => 'application/json',
          'canary'            => $apiCanary,
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
        self::throwNotOk($response, 'Benutzername abgelehnt');

        /********************************************************************************************
         * 3: login with username/password
         ********************************************************************************************/

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
        self::throwNotOk($response, 'Anmeldedaten abgelehnt');

        $body = $response->getBody()->getContents();

        self::throwIfMissing($body, 'PhoneAppOTP', 'TOTP nicht unterstützt');
        self::throwIfMissing($body, 'iTotpOtcLength', 'TOTP nicht unterstützt');

        $msRequestId = $response->getHeaderLine('x-ms-request-id') ?: self::throwAuthPro('MsRequestId', 3);
        $clientId = self::extractValue($body, 'client-request-id=', '\u0026') ?: self::throwAuthPro('clientId', 3);
        $apiCanary = self::extractValue($body, '"apiCanary":"', '"') ?: self::throwAuthPro('apiCanary', 3);
        $hpgid = self::extractValue($body, '"hpgid":', ',') ?: self::throwAuthPro('hpgid', 3);
        $hpgact = self::extractValue($body, '"hpgact":', ',') ?: self::throwAuthPro('hpgact', 3);
        $ctx = self::extractValue($body, '\u0026ctx=', '"') ?: self::throwAuthPro('ctx', 3);
        $flowToken = self::extractValue($body, '"sFT":"', '"') ?: self::throwAuthPro('sFT', 3);

        /********************************************************************************************
         * 4: request MFA via TOTP
         ********************************************************************************************/

        $totpHeaders = [
          'Accept'            => 'application/json',
          'canary'            => $apiCanary,
          'client-request-id' => $clientId,
          'hpgact'            => $hpgact,
          'hpgid'             => $hpgid,
          'hprequestid'       => $msRequestId,
        ];
        $totpBody = [
          'AuthMethodId'  => 'PhoneAppOTP',
          'ctx'           => $ctx,
          'flowToken'     => $flowToken,
          'Method'        => 'BeginAuth',
        ];

        $response = self::$client->post('https://login.microsoftonline.com/common/SAS/BeginAuth', [
          'headers' => $totpHeaders,
          'json' => $totpBody,
        ]);
        self::throwNotOk($response, 'TOTP nicht erlaubt bei diesem Benutzer');

        $body = $response->getBody()->getContents();

        self::throwIfMissing($body, '"ResultValue":"Success"', 'Fehler beim Abrufen der TOTP-Parameter');

        $ctx = self::extractValue($body, '"Ctx":"', '"') ?: self::throwAuthPro('Ctx', 4);
        $flowToken = self::extractValue($body, '"FlowToken":"', '"') ?: self::throwAuthPro('FlowToken', 4);
        $sessionId = self::extractValue($body, '"SessionId":"', '"') ?: self::throwAuthPro('SessionId', 4);
        $totp = self::$totp_sec->now();

        /********************************************************************************************
         * 5: check TOTP is valid
         ********************************************************************************************/

        $totpBody = [
          'AdditionalAuthData'  => $totp,
          'AuthMethodId'        => 'PhoneAppOTP',
          'Ctx'                 => $ctx,
          'FlowToken'           => $flowToken,
          'Method'              => 'EndAuth',
          'PollCount'           => 1,
          'SessionId'           => $sessionId,
        ];

        $response = self::$client->post('https://login.microsoftonline.com/common/SAS/EndAuth', [
          'headers' => $totpHeaders,
          'json' => $totpBody,
        ]);
        self::throwNotOk($response, 'TOTP abgelehnt');

        $body = $response->getBody()->getContents();

        self::throwIfMissing($body, '"ResultValue":"Success"', 'Fehler beim Abrufen der TOTP-Parameter');

        $ctx = self::extractValue($body, '"Ctx":"', '"') ?: self::throwAuthPro('Ctx', 5);
        $flowToken = self::extractValue($body, '"FlowToken":"', '"') ?: self::throwAuthPro('FlowToken', 5);
        $sessionId = self::extractValue($body, '"SessionId":"', '"') ?: self::throwAuthPro('SessionId', 5);

        /********************************************************************************************
         * 6: process TOTP
         ********************************************************************************************/

        $processBody = [
          'type' => 19,
          'GeneralVerify' => false,
          'request' => $ctx,
          'mfaLastPollStart' => Carbon::now()->subSeconds(2)->timestamp,
          'mfaLastPollEnd' => Carbon::now()->subSeconds(1)->timestamp,
          'mfaAuthMethod' => 'PhoneAppOTP',
          'otc' => $totp,
          'login' => self::$username,
          'flowToken' => $flowToken,
          'hpgrequestid' => $msRequestId,
          'sacxt' => '',
          'hideSmsInMfaProofs' => false,
          'canary' => $loginCanary,
          'i19' => '10318',
        ];

        $response = self::$client->post('https://login.microsoftonline.com/common/SAS/ProcessAuth', [
          'form_params' => $processBody,
        ]);
        self::throwNotOk($response, 'TOTP abgelehnt');

        $body = $response->getBody()->getContents();

        self::throwIfMissing($body, '/_forms/default.aspx', 'Die Weiterleitung nach der Anmeldung schlug fehl.');

        $msRequestId = $response->getHeaderLine('x-ms-request-id') ?: self::throwAuthPro('MsRequestId', 6);
        $code = self::extractValue($body, 'name="code" value="', '"') ?: self::throwAuthPro('code', 6);
        $idToken = self::extractValue($body, 'name="id_token" value="', '"') ?: self::throwAuthPro('id_token', 6);
        $sessionState = self::extractValue($body, 'name="session_state" value="', '"') ?: self::throwAuthPro('session_state', 6);
        $correlation = self::extractValue($body, 'name="correlation_id" value="', '"') ?: self::throwAuthPro('correlation_id', 6);

        /********************************************************************************************
         * 7: open sharepoint
         ********************************************************************************************/

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
        self::throwNotOk($response, 'Anmeldung abgelehnt');

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

    private static function throwAuthPro(string $prop, int $step)
    {
      throw new AuthFailure("Prop '$prop' in Schritt $step nicht gefunden");
    }

    private static function throwNotOk($response, string $reason = 'Status nicht 200')
    {
      if ($response->getStatusCode() !== 200) {
        throw new AuthFailure($reason);
      }
    }

    private static function throwIfMissing(string $body, string $contains, string $reason)
    {
      if (stripos($body, $contains) === false) {
        throw new AuthFailure($reason);
      }
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
        self::throwNotOk($response, 'Kalenderabruf abgelehnt');

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

    if (strpos($title, 'MDR') !== false && !$description)
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

