<?php

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
