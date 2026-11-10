<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Autocount;
use Jiannius\Autocount\Exceptions\AutocountLoginFailed;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

function traitUris() : array
{
    $uris = [];

    foreach (glob(__DIR__.'/../src/Traits/*.php') as $file) {
        preg_match_all("/uri:\s*'([^']+)'/", file_get_contents($file), $matches);

        array_push($uris, ...$matches[1]);
    }

    return array_values(array_unique($uris));
}

it('lists every Get endpoint the traits call as a read', function () {
    $gets = array_values(array_filter(traitUris(), fn ($uri) => str_starts_with(basename($uri), 'Get')));

    sort($gets);

    $reads = Autocount::READS;
    sort($reads);

    expect($gets)->not->toBeEmpty()
        ->and($reads)->toBe($gets)
        ->and(Autocount::readEndpoints())->toBe(Autocount::READS);
});

it('lists no write endpoint as a read', function () {
    foreach (Autocount::READS as $uri) {
        expect(basename($uri))->toStartWith('Get');
    }
});

it('ensureToken returns the seconds left on a cached token and does not log in', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    $left = autocount()->ensureToken();

    expect($left)->toBeGreaterThan(3 * 86400 - 5)->toBeLessThanOrEqual(3 * 86400);

    Http::assertNothingSent();
});

it('ensureToken logs in for a fresh token when none is cached', function () {
    Http::fake(['*/Server/Login' => Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]])]);

    $left = autocount()->ensureToken();

    expect($left)->toBeGreaterThan(7 * 86400 - 5)->toBeLessThanOrEqual(7 * 86400)
        ->and(loginRequests())->toHaveCount(1);
});

it('ensureToken logs in again when the cached token has expired', function () {
    Cache::put('autocount_token_app-1', jwt(now()->subHour()->timestamp));

    Http::fake(['*/Server/Login' => Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]])]);

    expect(autocount()->ensureToken())->toBeGreaterThan(86400)
        ->and(loginRequests())->toHaveCount(1);
});

it('ensureToken assumes an hour when the expiry cannot be read', function () {
    Http::fake(['*/Server/Login' => Http::response([['JWTToken' => 'not-a-jwt']])]);

    expect(autocount()->ensureToken())->toBe(3600);
});

it('ensureToken throws AutocountLoginFailed when the login fails', function () {
    Http::fake(['*/Server/Login' => Http::response('nope', 401)]);

    expect(fn () => autocount()->ensureToken())->toThrow(AutocountLoginFailed::class);
});
