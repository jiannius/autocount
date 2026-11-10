<?php

namespace Jiannius\Autocount;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Exceptions\AutocountLoginFailed;
use Jiannius\Autocount\Exceptions\AutocountRefused;
use Jiannius\Autocount\Exceptions\AutocountServerError;
use Jiannius\Autocount\Exceptions\AutocountUnreachable;
use Jiannius\Autocount\Traits\APCN;
use Jiannius\Autocount\Traits\APDN;
use Jiannius\Autocount\Traits\APInvoice;
use Jiannius\Autocount\Traits\APPayment;
use Jiannius\Autocount\Traits\ARCN;
use Jiannius\Autocount\Traits\ARInvoice;
use Jiannius\Autocount\Traits\ARPayment;
use Jiannius\Autocount\Traits\ARDN;
use Jiannius\Autocount\Traits\ARRefund;
use Jiannius\Autocount\Traits\CashBook;
use Jiannius\Autocount\Traits\CashSales;
use Jiannius\Autocount\Traits\CreditNote;
use Jiannius\Autocount\Traits\Creditor;
use Jiannius\Autocount\Traits\DebitNote;
use Jiannius\Autocount\Traits\Debtor;
use Jiannius\Autocount\Traits\Invoice;
use Jiannius\Autocount\Traits\Item;
use Jiannius\Autocount\Traits\Project;
use Jiannius\Autocount\Traits\PurchaseInvoice;
use Jiannius\Autocount\Traits\TaxEntity;

class Autocount
{
    use APCN;
    use APDN;
    use APInvoice;
    use APPayment;
    use ARCN;
    use ARInvoice;
    use ARDN;
    use ARPayment;
    use ARRefund;
    use CashBook;
    use CashSales;
    use CreditNote;
    use Creditor;
    use DebitNote;
    use Debtor;
    use Invoice;
    use Item;
    use Project;
    use PurchaseInvoice;
    use TaxEntity;

    /**
     * Every read endpoint the traits call — the ones whose last segment is `Get…`
     *
     * Reads change nothing in Autocount, so a consumer may retry them freely; every
     * other endpoint is a write. tests/ReadsTest.php fails when a trait calls a Get
     * endpoint that is not listed here.
     */
    public const READS = [
        'APCreditNote/GetAPCreditNote',
        'APDebitNote/GetAPDebitNote',
        'APInvoice/GetAPInvoice',
        'APPayment/GetAPPayment',
        'ARCreditNote/GetARCreditNote',
        'ARDebitNote/GetARDebitNote',
        'ARInvoice/GetARInvoice',
        'ARPayment/GetARPayment',
        'ARRefund/GetARRefund',
        'CashBook/GetCashBook',
        'CashSales/GetCashSales',
        'CreditNote/GetCreditNote',
        'Creditor/GetCreditor',
        'DebitNote/GetDebitNote',
        'Debtor/GetDebtor',
        'Invoice/GetInvoice',
        'Project/GetProject',
        'PurchaseInvoice/GetPurchaseInvoice',
        'TaxEntity/GetTaxEntity',
        'V2/Item/GetItem',
    ];

    public $settings = [];

    /**
     * Whether the token last handed out by getToken() was minted by a login
     */
    protected $tokenWasMinted = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settings = [
            'url' => config('services.autocount.url'),
            'app_id' => config('services.autocount.app_id'),
            'user_id' => config('services.autocount.user_id'),
            'password' => config('services.autocount.password'),
            'failed_callback' => null,
            'timeout' => null,
            'connect_timeout' => null,
            'request_listener' => null,
        ];
    }

    /**
     * Set the URL
     */
    public function setUrl($value)
    {
        $this->settings['url'] = $value;
        return $this;
    }

    /**
     * Set the App ID
     */
    public function setAppId($value)
    {
        $this->settings['app_id'] = $value;
        return $this;
    }

    /**
     * Set the user ID
     */
    public function setUserId($value)
    {
        $this->settings['user_id'] = $value;
        return $this;
    }

    /**
     * Set the password
     */
    public function setPassword($value)
    {
        $this->settings['password'] = $value;
        return $this;
    }

    /**
     * Set the failed callback
     */
    public function setFailedCallback($value)
    {
        $this->settings['failed_callback'] = $value;
        return $this;
    }

    /**
     * Set the request timeout, in seconds
     *
     * Applies to every request including the login call. Unset, Laravel's default
     * (30 seconds) stands.
     */
    public function setTimeout(int $seconds)
    {
        $this->settings['timeout'] = $seconds;
        return $this;
    }

    /**
     * Set the connect timeout, in seconds
     *
     * Applies to every request including the login call. Unset, Laravel's default
     * (10 seconds) stands.
     */
    public function setConnectTimeout(int $seconds)
    {
        $this->settings['connect_timeout'] = $seconds;
        return $this;
    }

    /**
     * Listen to every HTTP attempt
     *
     * The listener is called once per HTTP attempt — the login call, the request, and
     * the replay of the request after an expired token — with exactly four arguments:
     *
     *   string  $uri          the endpoint, e.g. 'ARInvoice/GetARInvoice'; the login
     *                         call is 'Server/Login'
     *   ?int    $status       the HTTP status, null when there was no response
     *   int     $durationMs   wall time of the attempt, in milliseconds
     *   string  $outcome      one of:
     *
     *     ok             answered, no Status Fail
     *     refused        answered 2xx with Status Fail
     *     expired_token  a cached token was rejected (401, or 500 "Stream was not
     *                    readable"); the next call with the same uri is its replay
     *     http_error     any other 4xx
     *     server_error   5xx, or a 2xx whose body is not JSON
     *     unreachable    connection error or timeout; $status is null
     *
     * Never the body, headers, token or payload. A listener that throws is ignored.
     * One listener is kept; calling this again replaces it.
     */
    public function onRequest(callable $listener)
    {
        $this->settings['request_listener'] = $listener;
        return $this;
    }

    /**
     * Get the settings
     */
    public function getSettings($key = null)
    {
        return $key ? data_get($this->settings, $key) : $this->settings;
    }

    /**
     * Get the endpoint
     */
    public function getEndpoint($uri)
    {
        throw_if(!$this->getSettings('url'), AutocountLoginFailed::class, 'Missing Autocount API Server URL');

        $tail = '/api/';
        $base = str($this->getSettings('url').$tail)->finish($tail);

        return $base.$uri;
    }

    /**
     * Get the cache key
     */
    public function getCacheKey()
    {
        $appId = $this->getSettings('app_id');

        throw_if(!$appId, AutocountLoginFailed::class, 'Missing Autocount App ID');

        return 'autocount_token_'.$appId;
    }

    /**
     * Get the token
     */
    public function getToken()
    {
        $cachekey = $this->getCacheKey();
        $cache = Cache::get($cachekey);

        if ($cache && $this->getTokenTtl($cache) !== 0) {
            $this->tokenWasMinted = false;

            return $cache;
        }

        Cache::forget($cachekey);

        $userId = $this->getSettings('user_id');
        $password = $this->getSettings('password');

        throw_if(!$userId || !$password, AutocountLoginFailed::class, 'Missing Autocount User ID / Password');

        $url = $this->getEndpoint('Server/Login');

        // Nothing but credentials has been sent at this point, so any failure here is
        // an AutocountLoginFailed — the real request never left.
        try {
            $response = $this->track('Server/Login', fn () => $this->applyTimeouts(Http::withHeader('AppID', $this->getSettings('app_id')))->post(url: $url, data: [
                'UserID' => $userId,
                'Password' => $password,
            ]), login: true)->throw();
        }
        catch (ConnectionException|RequestException $e) {
            throw new AutocountLoginFailed($e->getMessage(), 0, $e);
        }

        $token = data_get($response->json(), '0.JWTToken');

        throw_if(!$token, AutocountLoginFailed::class, 'Unable to get Autocount Token from API Server');

        $ttl = $this->getTokenTtl($token);

        if ($ttl !== 0) Cache::put($cachekey, $token, $ttl);

        $this->tokenWasMinted = true;

        return $token;
    }

    /**
     * Make sure there is a usable token, and say how long it has left
     *
     * Takes the cached token or logs in for a fresh one (so it throws
     * AutocountLoginFailed when that fails), and returns the real seconds until the
     * token's JWT `exp` — not the shorter cache window of getTokenTtl(). When `exp`
     * cannot be read it returns 3600, the same one-hour assumption getTokenTtl() makes.
     */
    public function ensureToken() : int
    {
        $exp = $this->getTokenExp($this->getToken());

        return $exp === null ? 3600 : max(0, $exp - time());
    }

    /**
     * The read endpoints, see READS
     */
    public static function readEndpoints() : array
    {
        return static::READS;
    }

    /**
     * The JWT `exp` of a token, or null when it cannot be read
     */
    protected function getTokenExp($token) : ?int
    {
        $payload = explode('.', (string) $token)[1] ?? '';
        $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $exp = is_array($payload) ? data_get($payload, 'exp') : null;

        return is_numeric($exp) ? (int) $exp : null;
    }

    /**
     * Get the seconds a token may still be cached for
     *
     * Tokens are JWTs that last 7 days. Once one expires Autocount does not answer
     * 401 — it answers every endpoint with 500 "Stream was not readable" — so the
     * token must leave the cache before its `exp`. Returns 0 when it is already
     * (or about to be) expired, and an hour when `exp` cannot be read.
     */
    protected function getTokenTtl($token) : int
    {
        $exp = $this->getTokenExp($token);

        if ($exp === null) return 3600;

        return max(0, $exp - time() - 300);
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        if ($this->getToken()) {
            Cache::forget($this->getCacheKey());
            return true;
        };

        return false;
    }

    /**
     * Is this exception Autocount telling us the record simply isn't there?
     *
     * Absence comes back through the same Fail/Message channel as a real error, and
     * is phrased differently per endpoint:
     *
     *   most lookups          "... not found"
     *   CashBook/GetCashBook  "Get Cash Book Entry Record : The source contains no
     *                          DataRows."
     *
     * The second is a .NET DataTable error surfacing verbatim — it means the query
     * matched zero rows, which for a getX() is absence, not failure.
     *
     * This used to be a `*not-found*` slug test copy-pasted into fifteen traits, so
     * the DataRows phrasing fell through and rethrew. That made an existence probe
     * fail precisely when the record did not exist: smgcrm's createCashBook() asks
     * "does this cash book exist?" to choose create vs update, and could therefore
     * never create a new one (reported 18 Aug 2026, payment FR-2607-0001-KI-BG-SR).
     *
     * Add new phrasings here, not in the traits.
     */
    public function isRecordNotFound(\Throwable $e) : bool
    {
        // No answer from Autocount is never "the record isn't there".
        if ($e instanceof ConnectionException || $e instanceof AutocountUnreachable) return false;

        $message = str($e->getMessage());

        return $message->slug()->is('*not-found*')
            || $message->lower()->contains('contains no datarows');
    }

    /**
     * Call the API
     *
     * Failures surface as one of:
     *
     *   AutocountLoginFailed   no token could be obtained; nothing was sent
     *   AutocountUnreachable   connection error / timeout (previous: ConnectionException)
     *   AutocountServerError   5xx, or a reply that is not JSON (previous: RequestException)
     *   RequestException       any other HTTP failure (4xx), unchanged
     *   AutocountRefused       HTTP 2xx with `Status: Fail`; message is Autocount's own
     *
     * When a failed callback is set it receives the failed response instead of the
     * HTTP-level exception being thrown.
     */
    public function callApi($uri, $method = 'GET', $data = []) : mixed
    {
        $result = $this->attempt($uri, $method, $data);

        // An expired token is rejected before Autocount runs the handler, so nothing
        // was posted and replaying the same request once is safe. That is observed,
        // not documented: seen in smgdms prod on 2026-10-13 (Project/GetProject after
        // the token expired) and in legacy smgcrm on every endpoint. Any other
        // failure, and a token minted during this very call, surfaces as it did before.
        if ($this->isExpiredTokenResponse($result) && !$this->tokenWasMinted) {
            Cache::forget($this->getCacheKey());

            $result = $this->attempt($uri, $method, $data);
        }

        // system level fail
        if ($result->failed()) {
            if ($callback = $this->getSettings('failed_callback')) $result = $callback($result);
            else throw $this->failureFor($result);
        }
        elseif ($this->isUnreadable($result)) {
            throw new AutocountServerError('Autocount returned a reply that is not valid JSON', $result->status());
        }

        // response level fail
        $status = data_get($result->json(), 'Status');
        $message = data_get($result->json(), 'Message');

        throw_if($status === 'Fail', AutocountRefused::class, $message);

        return $result;
    }

    /**
     * Send one request, turning a transport failure into AutocountUnreachable
     */
    protected function attempt($uri, $method, $data)
    {
        try {
            return $this->sendRequest($uri, $method, $data);
        }
        catch (ConnectionException $e) {
            throw new AutocountUnreachable($e->getMessage(), 0, $e);
        }
    }

    /**
     * The exception for a failed HTTP response
     *
     * A 5xx is an AutocountServerError (outcome unknown). Anything else, a 4xx, stays
     * the RequestException it always was — a definite rejection that consumers
     * already treat as such.
     */
    protected function failureFor($result) : \Throwable
    {
        $e = $result->toException();

        return $result->serverError()
            ? new AutocountServerError($e->getMessage(), $result->status(), $e)
            : $e;
    }

    /**
     * Did a successful response carry a body that is not JSON?
     *
     * An empty body is let through, as it always was.
     */
    protected function isUnreadable($result) : bool
    {
        $body = trim($result->body());

        if ($body === '') return false;

        json_decode($body);

        return json_last_error() !== JSON_ERROR_NONE;
    }

    /**
     * Is this response Autocount rejecting an expired token?
     *
     * It answers 500 "Stream was not readable" for every endpoint, not 401.
     */
    protected function isExpiredTokenResponse($result) : bool
    {
        return $result->status() === 401
            || ($result->status() === 500 && str_contains($result->body(), 'Stream was not readable'));
    }

    /**
     * Send one request to the API
     */
    protected function sendRequest($uri, $method, $data)
    {
        $method = strtolower($method);
        $token = $this->getToken();

        if (!$token) abort(500, 'Missing Autocount Token');

        $url = $this->getEndpoint($uri);
        $request = $this->applyTimeouts(Http::withHeaders([
            'Authorization' => $token,
            'AppId' => $this->getSettings('app_id'),
        ]));

        // A whole amount must stay a decimal on the wire. json_encode() drops the
        // fractional zero, so 89888.00 goes out as the token 89888, and Autocount
        // infers each field's type from the FIRST record — one integer there and
        // the whole set is read as integer, rounding every later value. A knockoff
        // of 89888.00 + 4441.96 became 89888 + 4442 and was rejected with
        // "PaymentAmt > Outstanding", because 4442 exceeds the 4441.96 the debit
        // note actually had. Confirmed by AutoCount support 2026-08-01; the same
        // payload with JSON_PRESERVE_ZERO_FRACTION was accepted.
        //
        // GET has no body — there $data is the query string.
        return $this->track($uri, fn () => $method === 'get'
            ? $request->get($url, $data)
            : $request
                ->withBody(json_encode($data, JSON_PRESERVE_ZERO_FRACTION), 'application/json')
                ->$method($url));
    }

    /**
     * Apply the configured timeouts to a request
     *
     * Protected so a subclass that overrides sendRequest() can still honour
     * setTimeout() / setConnectTimeout() by passing its own request through here.
     */
    protected function applyTimeouts(PendingRequest $request) : PendingRequest
    {
        if (($timeout = $this->getSettings('timeout')) !== null) $request->timeout($timeout);
        if (($timeout = $this->getSettings('connect_timeout')) !== null) $request->connectTimeout($timeout);

        return $request;
    }

    /**
     * Run one HTTP attempt and report it to the request listener
     *
     * sendRequest() and the login call go through here. A subclass that replaces
     * sendRequest() wholesale gets no listener events for its own request (the login
     * call still reports); wrap the send in track() to keep them.
     */
    protected function track(string $uri, callable $send, bool $login = false)
    {
        $start = hrtime(true);

        try {
            $response = $send();
        }
        catch (ConnectionException $e) {
            $this->notify($uri, null, $start, 'unreachable');

            throw $e;
        }

        $this->notify($uri, $response->status(), $start, $this->outcomeOf($response, $login));

        return $response;
    }

    /**
     * Classify one HTTP attempt for the request listener
     */
    protected function outcomeOf($response, bool $login) : string
    {
        if (!$response->failed()) {
            if ($this->isUnreadable($response)) return 'server_error';

            return data_get($response->json(), 'Status') === 'Fail' ? 'refused' : 'ok';
        }

        if (!$login && $this->isExpiredTokenResponse($response) && !$this->tokenWasMinted) return 'expired_token';

        return $response->serverError() ? 'server_error' : 'http_error';
    }

    /**
     * Tell the request listener, swallowing anything it throws
     */
    protected function notify(string $uri, ?int $status, int|float $start, string $outcome) : void
    {
        if (!is_callable($listener = $this->getSettings('request_listener'))) return;

        try {
            $listener($uri, $status, (int) round((hrtime(true) - $start) / 1e6), $outcome);
        }
        catch (\Throwable) {
            // observing a request must never break it
        }
    }
}
