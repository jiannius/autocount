<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Autocount;
use Jiannius\Autocount\Exceptions\AutocountLoginFailed;
use Jiannius\Autocount\Exceptions\AutocountRefused;
use Jiannius\Autocount\Exceptions\AutocountServerError;
use Jiannius\Autocount\Exceptions\AutocountUnreachable;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));
});

function caught(callable $fn) : ?Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

it('has the exception short names consumers rely on, all plain exceptions', function () {
    foreach (['Refused', 'LoginFailed', 'Unreachable', 'ServerError'] as $name) {
        $class = "Jiannius\\Autocount\\Exceptions\\Autocount{$name}";

        expect(is_subclass_of($class, Exception::class))->toBeTrue();
    }
});

it('throws AutocountRefused with the raw message on Status Fail', function () {
    Http::fake(['*/Debtor/Get' => Http::response(['Status' => 'Fail', 'Message' => 'Debtor 300-A001 is locked'])]);

    $e = caught(fn () => autocount()->callApi('Debtor/Get', 'POST'));

    expect($e)->toBeInstanceOf(AutocountRefused::class)
        ->and($e->getMessage())->toBe('Debtor 300-A001 is locked');
});

it('throws AutocountRefused from a trait level Status Fail check with the raw message', function () {
    Http::fake(['*/Debtor' => Http::response([['Status' => 'Fail', 'Message' => 'Duplicate AccNo']])]);

    $e = caught(fn () => autocount()->createDebtor(['AccNo' => '300-A001']));

    expect($e)->toBeInstanceOf(AutocountRefused::class)
        ->and($e->getMessage())->toBe('Duplicate AccNo');
});

it('throws AutocountLoginFailed when the login call is rejected, keeping the original', function () {
    Cache::flush();

    Http::fake(['*/Server/Login' => Http::response(['Message' => 'Invalid password'], 400)]);

    $e = caught(fn () => autocount()->callApi('Debtor/Get', 'POST'));

    expect($e)->toBeInstanceOf(AutocountLoginFailed::class)
        ->and($e->getPrevious())->toBeInstanceOf(RequestException::class)
        ->and(apiRequests())->toHaveCount(0);
});

it('throws AutocountLoginFailed when the login reply has no token', function () {
    Cache::flush();

    Http::fake(['*/Server/Login' => Http::response([['Something' => 'else']])]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST'))->toThrow(AutocountLoginFailed::class);

    expect(apiRequests())->toHaveCount(0);
});

it('throws AutocountLoginFailed when the login call cannot connect', function () {
    Cache::flush();

    Http::fake(['*/Server/Login' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $e = caught(fn () => autocount()->callApi('Debtor/Get', 'POST'));

    expect($e)->toBeInstanceOf(AutocountLoginFailed::class)
        ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
});

it('throws AutocountLoginFailed for missing configuration, before anything is sent', function (callable $configure) {
    Cache::flush();

    $autocount = $configure(autocount());

    expect(fn () => $autocount->callApi('Debtor/Get', 'POST'))->toThrow(AutocountLoginFailed::class);

    Http::assertNothingSent();
})->with([
    'url' => [fn (Autocount $a) => $a->setUrl(null)],
    'app id' => [fn (Autocount $a) => $a->setAppId(null)],
    'user id' => [fn (Autocount $a) => $a->setUserId(null)],
    'password' => [fn (Autocount $a) => $a->setPassword(null)],
]);

it('throws AutocountUnreachable on a connection error, keeping the original', function () {
    Http::fake(['*/Debtor/Get' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $e = caught(fn () => autocount()->callApi('Debtor/Get', 'POST'));

    expect($e)->toBeInstanceOf(AutocountUnreachable::class)
        ->and($e->getMessage())->toContain('timed out')
        ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
});

it('throws AutocountServerError on a 5xx, keeping the original', function () {
    Http::fake(['*/Debtor/Get' => Http::response('boom', 502)]);

    $e = caught(fn () => autocount()->callApi('Debtor/Get', 'POST'));

    expect($e)->toBeInstanceOf(AutocountServerError::class)
        ->and($e->getCode())->toBe(502)
        ->and($e->getPrevious())->toBeInstanceOf(RequestException::class);
});

it('throws AutocountServerError when a successful reply is not JSON', function () {
    Http::fake(['*/Debtor/Get' => Http::response('<html>Gateway</html>', 200)]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST'))->toThrow(AutocountServerError::class);
});

it('lets an empty successful reply through as it always did', function () {
    Http::fake(['*/Debtor/Get' => Http::response('', 200)]);

    expect(autocount()->callApi('Debtor/Get', 'POST')->status())->toBe(200);
});

it('leaves a 4xx as the original RequestException', function () {
    Http::fake(['*/Debtor/Get' => Http::response(['Message' => 'Bad request'], 422)]);

    expect(fn () => autocount()->callApi('Debtor/Get', 'POST'))->toThrow(RequestException::class);
});

it('hands a failed response to the failed callback instead of throwing', function () {
    Http::fake(['*/Debtor/Get' => Http::response('unavailable', 503)]);

    $seen = null;

    autocount()->setFailedCallback(function ($response) use (&$seen) {
        $seen = $response->status();

        return $response;
    })->callApi('Debtor/Get', 'POST');

    expect($seen)->toBe(503);
});

it('rethrows a getX() transport error typed, not as a plain exception', function () {
    Http::fake(['*/ARCreditNote/GetARCreditNote' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    $e = caught(fn () => autocount()->getARCNs('CN-1'));

    expect($e)->toBeInstanceOf(AutocountUnreachable::class)
        ->and($e::class)->not->toBe(Exception::class);
});

it('rethrows a getX() server error and refusal typed', function () {
    Http::fake(['*/ARCreditNote/GetARCreditNote' => Http::sequence()
        ->push('boom', 500)
        ->push(['Status' => 'Fail', 'Message' => 'Access denied']),
    ]);

    expect(fn () => autocount()->getARCNs('CN-1'))->toThrow(AutocountServerError::class);
    expect(fn () => autocount()->getARCNs('CN-1'))->toThrow(AutocountRefused::class, 'Access denied');
});

it('still returns an empty list from getX() when the record is not found', function (string $message) {
    Http::fake(['*/ARCreditNote/GetARCreditNote' => Http::response(['Status' => 'Fail', 'Message' => $message])]);

    expect(autocount()->getARCNs('CN-1'))->toBe([]);
})->with([
    'not found' => ['Credit note CN-1 not found'],
    'no datarows' => ['Get Record : The source contains no DataRows.'],
]);

it('does not mistake a transport error for a missing record', function () {
    Http::fake(['*/ARCreditNote/GetARCreditNote' => fn () => throw new ConnectionException('Host not found')]);

    expect(fn () => autocount()->getARCNs('CN-1'))->toThrow(AutocountUnreachable::class);
});
