<?php

namespace Jiannius\Autocount;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Traits\ARPayment;
use Jiannius\Autocount\Traits\CashSales;
use Jiannius\Autocount\Traits\CreditNote;
use Jiannius\Autocount\Traits\Creditor;
use Jiannius\Autocount\Traits\DebitNote;
use Jiannius\Autocount\Traits\Debtor;
use Jiannius\Autocount\Traits\Invoice;
use Jiannius\Autocount\Traits\Item;
use Jiannius\Autocount\Traits\Project;
use Jiannius\Autocount\Traits\PurchaseInvoice;

class Autocount
{
    use ARPayment;
    use CashSales;
    use CreditNote;
    use Creditor;
    use DebitNote;
    use Debtor;
    use Invoice;
    use Item;
    use Project;
    use PurchaseInvoice;

    public $settings = [];

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
        throw_if(!$this->getSettings('url'), \Exception::class, 'Missing Autocount API Server URL');

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

        throw_if(!$appId, \Exception::class, 'Missing Autocount App ID');

        return 'autocount_token_'.$appId;
    }

    /**
     * Get the token
     */
    public function getToken()
    {
        $cachekey = $this->getCacheKey();
        $cache = Cache::get($cachekey);

        if ($cache) return $cache;

        Cache::forget($cachekey);

        $userId = $this->getSettings('user_id');
        $password = $this->getSettings('password');

        throw_if(!$userId || !$password, \Exception::class, 'Missing Autocount User ID / Password');

        $url = $this->getEndpoint('Server/Login');

        $response = Http::withHeader('AppID', $this->getSettings('app_id'))->post(url: $url, data: [
            'UserID' => $userId,
            'Password' => $password,
        ])->throw();

        $token = data_get($response->json(), '0.JWTToken');

        throw_if(!$token, \Exception::class, 'Unable to get Autocount Token from API Server');

        Cache::put($cachekey, $token);

        return $token;
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
     * Call the API
     */
    public function callApi($uri, $method = 'GET', $data = []) : mixed
    {
        $method = strtolower($method);
        $token = $this->getToken();

        if (!$token) abort(500, 'Missing Autocount Token');

        $url = $this->getEndpoint($uri);
        $result = Http::withHeaders([
            'Authorization' => $token,
            'AppId' => $this->getSettings('app_id'),
        ])->$method($url, $data);

        // system level fail
        if ($result->failed()) {
            if ($callback = $this->getSettings('failed_callback')) $result = $callback($result);
            else $result->throw();
        }

        // response level fail
        $status = data_get($result->json(), 'Status');
        $message = data_get($result->json(), 'Message');

        throw_if($status === 'Fail', \Exception::class, $message);

        return $result;
    }
}
