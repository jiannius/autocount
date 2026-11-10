<?php

namespace Jiannius\Autocount;

use Illuminate\Http\Client\ConnectionException;
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
            $response = Http::withHeader('AppID', $this->getSettings('app_id'))->post(url: $url, data: [
                'UserID' => $userId,
                'Password' => $password,
            ])->throw();
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
     * Get the seconds a token may still be cached for
     *
     * Tokens are JWTs that last 7 days. Once one expires Autocount does not answer
     * 401 — it answers every endpoint with 500 "Stream was not readable" — so the
     * token must leave the cache before its `exp`. Returns 0 when it is already
     * (or about to be) expired, and an hour when `exp` cannot be read.
     */
    protected function getTokenTtl($token) : int
    {
        $payload = explode('.', (string) $token)[1] ?? '';
        $payload = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $exp = is_array($payload) ? data_get($payload, 'exp') : null;

        if (!is_numeric($exp)) return 3600;

        return max(0, (int) $exp - time() - 300);
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
        $request = Http::withHeaders([
            'Authorization' => $token,
            'AppId' => $this->getSettings('app_id'),
        ]);

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
        return $method === 'get'
            ? $request->get($url, $data)
            : $request
                ->withBody(json_encode($data, JSON_PRESERVE_ZERO_FRACTION), 'application/json')
                ->$method($url);
    }
}
