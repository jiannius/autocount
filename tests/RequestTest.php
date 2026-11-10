<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Exceptions\AutocountUnreachable;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
});

function listening(array &$events) : Closure
{
    return function (...$args) use (&$events) {
        $events[] = $args;
    };
}

it('keeps the default timeouts when none are set', function () {
    $seen = [];

    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake(['*/Debtor/Get' => function ($request, $options) use (&$seen) {
        $seen[] = [$options['timeout'], $options['connect_timeout']];

        return Http::response([]);
    }]);

    autocount()->callApi('Debtor/Get', 'POST');

    expect($seen)->toBe([[30, 10]]);
});

it('applies the timeouts to the login call and to the request, GET and POST', function () {
    $seen = [];

    $record = function ($request, $options) use (&$seen) {
        $seen[basename($request->url())][] = [$options['timeout'], $options['connect_timeout']];

        return Http::response([['JWTToken' => jwt(now()->addDays(7)->timestamp)]]);
    };

    Http::fake(['*/Server/Login' => $record, '*/Debtor/Get' => $record, '*/Debtor/Other' => $record]);

    $autocount = autocount()->setTimeout(7)->setConnectTimeout(3);

    expect($autocount)->toBeInstanceOf(Jiannius\Autocount\Autocount::class);

    $autocount->callApi('Debtor/Get', 'POST');
    $autocount->callApi('Debtor/Other', 'GET');

    expect($seen['Login'])->toBe([[7, 3]])
        ->and($seen['Get'])->toBe([[7, 3]])
        ->and($seen['Other'])->toBe([[7, 3]]);
});

it('lets a timed out request surface as AutocountUnreachable', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));

    Http::fake(['*/Debtor/Get' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

    expect(fn () => autocount()->setTimeout(1)->callApi('Debtor/Get', 'POST'))->toThrow(AutocountUnreachable::class);
});

it('tells the listener uri, status, duration and outcome of the login and the request', function () {
    $events = [];
    $token = jwt(now()->addDays(7)->timestamp);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $token]]),
        '*/Debtor/Get' => Http::response([]),
    ]);

    autocount()->onRequest(listening($events))->callApi('Debtor/Get', 'POST', ['DocNo' => 'SECRET-DOC']);

    expect($events)->toHaveCount(2)
        ->and($events[0][0])->toBe('Server/Login')
        ->and($events[0][1])->toBe(200)
        ->and($events[0][3])->toBe('ok')
        ->and($events[1][0])->toBe('Debtor/Get')
        ->and($events[1][1])->toBe(200)
        ->and($events[1][2])->toBeInt()->toBeGreaterThanOrEqual(0)
        ->and($events[1][3])->toBe('ok')
        ->and($events[1])->toHaveCount(4);

    $everything = json_encode($events);

    expect($everything)->not->toContain($token)
        ->and($everything)->not->toContain('SECRET-DOC')
        ->and($everything)->not->toContain('secret')
        ->and($everything)->not->toContain('Authorization');
});

it('reports every outcome', function (callable $fake, ?int $status, string $outcome) {
    $events = [];

    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));
    Http::fake(['*/Debtor/Get' => $fake]);

    try {
        autocount()->onRequest(listening($events))->callApi('Debtor/Get', 'POST');
    } catch (Throwable) {
    }

    expect($events)->toHaveCount(1)
        ->and($events[0][1])->toBe($status)
        ->and($events[0][3])->toBe($outcome);
})->with([
    'ok' => [fn () => Http::response([]), 200, 'ok'],
    'refused' => [fn () => Http::response(['Status' => 'Fail', 'Message' => 'No']), 200, 'refused'],
    'http_error' => [fn () => Http::response(['Message' => 'Bad'], 422), 422, 'http_error'],
    'server_error' => [fn () => Http::response('boom', 503), 503, 'server_error'],
    'unreadable reply' => [fn () => Http::response('<html>', 200), 200, 'server_error'],
    'unreachable' => [fn () => throw new ConnectionException('timed out'), null, 'unreachable'],
]);

it('reports the rejected attempt as expired_token and then the replay, and replays only once', function () {
    $events = [];
    $stale = jwt(now()->addDays(3)->timestamp);
    $fresh = jwt(now()->addDays(7)->timestamp);

    Cache::put('autocount_token_app-1', $stale);

    Http::fake([
        '*/Server/Login' => Http::response([['JWTToken' => $fresh]]),
        '*/Debtor/Get' => Http::sequence()
            ->push(['Message' => 'Stream was not readable.'], 500)
            ->push(['Message' => 'Stream was not readable.'], 500),
    ]);

    try {
        autocount()->onRequest(listening($events))->callApi('Debtor/Get', 'POST');
    } catch (Throwable) {
    }

    expect(array_map(fn ($e) => [$e[0], $e[1], $e[3]], $events))->toBe([
        ['Debtor/Get', 500, 'expired_token'],
        ['Server/Login', 200, 'ok'],
        ['Debtor/Get', 500, 'server_error'],
    ])
        ->and(apiRequests())->toHaveCount(2)
        ->and(loginRequests())->toHaveCount(1);
});

it('does not let a throwing listener break the request', function () {
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));
    Http::fake(['*/Debtor/Get' => Http::response(['ok' => true])]);

    $result = autocount()->onRequest(fn () => throw new RuntimeException('listener bug'))->callApi('Debtor/Get', 'POST');

    expect($result->json('ok'))->toBeTrue();
});
