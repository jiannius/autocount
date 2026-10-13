<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Autocount;

function jwt(int $exp) : string
{
    $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

    return $encode(['alg' => 'HS256', 'typ' => 'JWT']).'.'.$encode(['exp' => $exp]).'.signature';
}

function autocount() : Autocount
{
    return (new Autocount)
        ->setUrl('https://autocount.test')
        ->setAppId('app-1')
        ->setUserId('user')
        ->setPassword('secret');
}

function loginRequests() : Collection
{
    return Http::recorded(fn ($request) => str_ends_with($request->url(), 'Server/Login'))
        ->map(fn ($pair) => $pair[0]);
}

function apiRequests() : Collection
{
    return Http::recorded(fn ($request) => str_ends_with($request->url(), 'Debtor/Get'))
        ->map(fn ($pair) => $pair[0]);
}

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

it('logs in again when the cached token has expired', function () {
    $expired = jwt(now()->subHour()->timestamp);
    $fresh = jwt(now()->addDays(7)->timestamp);

    Cache::put('autocount_token_app-1', $expired);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $fresh]]),
        '*/Debtor/Get' => Http::response([]),
    ]);

    autocount()->callApi('Debtor/Get');

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(1)
        ->and(apiRequests()->first()->header('Authorization'))->toBe([$fresh]);
});

it('forgets the token, logs in again and retries once when the call fails', function () {
    $stale = jwt(now()->addDays(3)->timestamp);
    $fresh = jwt(now()->addDays(7)->timestamp);

    Cache::put('autocount_token_app-1', $stale);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $fresh]]),
        '*/Debtor/Get' => fn ($request) => $request->hasHeader('Authorization', $stale)
            ? Http::response(['ClassName' => 'System.ArgumentException', 'Message' => 'Stream was not readable.'], 500)
            : Http::response([]),
    ]);

    autocount()->callApi('Debtor/Get');

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(2)
        ->and(apiRequests()->last()->header('Authorization'))->toBe([$fresh])
        ->and(Cache::get('autocount_token_app-1'))->toBe($fresh);
});

it('retries only once when the call keeps failing', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]]),
        '*/Debtor/Get' => Http::response(['Message' => 'Stream was not readable.'], 500),
    ]);

    expect(fn () => autocount()->callApi('Debtor/Get'))->toThrow(RequestException::class);

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(2);
});

it('caches a fresh token until a few minutes before it expires', function () {
    $exp = now()->addDays(7)->startOfSecond();
    $fresh = jwt($exp->timestamp);

    Http::fake(['*/Server/Login' => Http::response([['JWTToken' => $fresh]])]);

    expect(autocount()->getToken())->toBe($fresh);

    $this->travelTo($exp->copy()->subMinutes(6));
    expect(Cache::get('autocount_token_app-1'))->toBe($fresh);

    $this->travelTo($exp->copy()->subMinutes(4));
    expect(Cache::get('autocount_token_app-1'))->toBeNull();
});

it('caches a token with no readable expiry for an hour', function () {
    Http::fake(['*/Server/Login' => Http::response([['JWTToken' => 'not-a-jwt']])]);

    autocount()->getToken();

    $this->travelTo(now()->addMinutes(59));
    expect(Cache::get('autocount_token_app-1'))->toBe('not-a-jwt');

    $this->travelTo(now()->addMinutes(2));
    expect(Cache::get('autocount_token_app-1'))->toBeNull();
});

it('replays a failed POST with the same body after an expired token is rejected', function () {
    $stale = jwt(now()->addDays(3)->timestamp);
    $fresh = jwt(now()->addDays(7)->timestamp);

    Cache::put('autocount_token_app-1', $stale);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $fresh]]),
        '*/Debtor/Get' => fn ($request) => $request->hasHeader('Authorization', $stale)
            ? Http::response(['ClassName' => 'System.ArgumentException', 'Message' => 'Stream was not readable.'], 500)
            : Http::response([]),
    ]);

    autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(2)
        ->and(apiRequests()->map->method()->unique()->values()->all())->toBe(['POST'])
        ->and(apiRequests()->first()->body())->toBe(apiRequests()->last()->body());
});

it('does not retry a POST rejected with a 400 and throws the original message', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake([
        '*/Debtor/Get' => Http::response(['Status' => 'Fail', 'Message' => 'Debtor code already exists'], 400),
    ]);

    $thrown = null;

    try {
        autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);
    } catch (RequestException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown->getMessage())->toContain('Debtor code already exists')
        ->and(loginRequests())->toHaveCount(0)
        ->and(apiRequests())->toHaveCount(1);
});

it('does not retry a POST rejected with a 503', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake(['*/Debtor/Get' => Http::response('unavailable', 503)]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]))
        ->toThrow(RequestException::class);

    expect(loginRequests())->toHaveCount(0)
        ->and(apiRequests())->toHaveCount(1);
});

it('lets a connection failure propagate without a retry', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    $attempts = 0;

    Http::fake(['*/Debtor/Get' => function () use (&$attempts) {
        $attempts++;

        throw new ConnectionException('cURL error 28: timed out');
    }]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]))
        ->toThrow(ConnectionException::class);

    expect(loginRequests())->toHaveCount(0)
        ->and($attempts)->toBe(1);
});

it('does not retry when the token was minted during the same call', function () {
    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]]),
        '*/Debtor/Get' => Http::response(['Message' => 'Stream was not readable.'], 500),
    ]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]))
        ->toThrow(RequestException::class);

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(1);
});

it('runs the failed callback once, on the replayed response', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]]),
        '*/Debtor/Get' => Http::sequence()
            ->push(['Message' => 'Stream was not readable.'], 500)
            ->push('unavailable', 503),
    ]);

    $seen = [];

    autocount()->setFailedCallback(function ($response) use (&$seen) {
        $seen[] = $response->status();

        return $response;
    })->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);

    expect($seen)->toBe([503])
        ->and(apiRequests())->toHaveCount(2);
});

it('still throws on an HTTP 200 with a Fail status', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake(['*/Debtor/Get' => Http::response(['Status' => 'Fail', 'Message' => 'Not allowed'])]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST', []))
        ->toThrow(\Exception::class, 'Not allowed');

    expect(loginRequests())->toHaveCount(0)
        ->and(apiRequests())->toHaveCount(1);
});

it('does not retry an unrelated 500 and keeps its message', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake([
        '*/Debtor/Get' => Http::response(['Message' => 'Object reference not set to an instance of an object.'], 500),
    ]);

    $thrown = null;

    try {
        autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);
    } catch (RequestException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull()
        ->and($thrown->getMessage())->toContain('Object reference not set to an instance of an object.')
        ->and(loginRequests())->toHaveCount(0)
        ->and(apiRequests())->toHaveCount(1);
});

it('replays once after a 401 on a cached token', function () {
    $fresh = jwt(now()->addDays(7)->timestamp);

    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $fresh]]),
        '*/Debtor/Get' => Http::sequence()->push('unauthorized', 401)->push([]),
    ]);

    autocount()->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(2);
});

it('retries on a later call of the same instance once its token comes from the cache', function () {
    $minted = jwt(now()->addDays(7)->timestamp);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $minted]]),
        '*/Debtor/Get' => Http::sequence()
            ->push([])
            ->push(['Message' => 'Stream was not readable.'], 500)
            ->push([]),
    ]);

    $autocount = autocount();

    $autocount->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);

    expect(loginRequests())->toHaveCount(1)
        ->and(apiRequests())->toHaveCount(1);

    $autocount->callApi('Debtor/Get', 'POST', ['Amount' => 10.0]);

    expect(loginRequests())->toHaveCount(2)
        ->and(apiRequests())->toHaveCount(3);
});
