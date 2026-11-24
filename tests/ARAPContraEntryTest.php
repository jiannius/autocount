<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Exceptions\AutocountRefused;
use Jiannius\Autocount\Exceptions\AutocountServerError;
use Jiannius\Autocount\Exceptions\AutocountUnreachable;

beforeEach(function () {
    Cache::flush();
    Http::preventStrayRequests();
    Cache::put('autocount_token_app-1', jwt(now()->addDays(3)->timestamp));
});

function contraPayload(array $extra = []) : array
{
    return $extra + [
        'DebtorCode' => '300-0001',
        'CreditorCode' => '400-2002',
        'JournalType' => 'GENERAL',
        'ARContraKnockOff' => [['DocNo' => 'I-000069', 'Amount' => 1.00]],
        'APContraKnockOff' => [['DocNo' => 'PI-000059', 'Amount' => 1.00]],
    ];
}

it('creates a contra entry by POSTing the payload to ARAPContraEntry', function () {
    Http::fake(['*/api/ARAPContraEntry' => Http::response(['Status' => 'Success', 'DocNo' => 'JV-000016'])]);

    $result = autocount()->createARAPContraEntry(contraPayload());

    expect($result)->toBe(['Status' => 'Success', 'DocNo' => 'JV-000016']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://autocount.test/api/ARAPContraEntry'
        && $request->hasHeader('AppId', 'app-1')
        && $request['DebtorCode'] === '300-0001'
        && $request['ARContraKnockOff'][0]['DocNo'] === 'I-000069'
        && $request['APContraKnockOff'][0]['DocNo'] === 'PI-000059');
});

it('updates a contra entry by POSTing to UpdateARAPContraEntry', function () {
    Http::fake(['*/ARAPContraEntry/UpdateARAPContraEntry' => Http::response(['Status' => 'Success'])]);

    autocount()->updateARAPContraEntry(contraPayload(['DocNo' => 'JV-000016']));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://autocount.test/api/ARAPContraEntry/UpdateARAPContraEntry'
        && $request['DocNo'] === 'JV-000016');
});

it('throws AutocountRefused with the raw message when Autocount refuses', function (string $method) {
    Http::fake(['*' => Http::response(['Status' => 'Fail', 'Message' => 'Knock off amount exceeds outstanding'])]);

    expect(fn () => autocount()->$method(contraPayload(['DocNo' => 'JV-000016'])))
        ->toThrow(AutocountRefused::class, 'Knock off amount exceeds outstanding');
})->with(['createARAPContraEntry', 'updateARAPContraEntry', 'deleteARAPContraEntries', 'cancelARAPContraEntries']);

it('throws AutocountServerError on a 5xx', function (string $method) {
    Http::fake(['*' => Http::response('boom', 500)]);

    expect(fn () => autocount()->$method(contraPayload()))->toThrow(AutocountServerError::class);
})->with(['createARAPContraEntry', 'updateARAPContraEntry']);

it('throws AutocountUnreachable on a connection failure', function (string $method) {
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

    expect(fn () => autocount()->$method(contraPayload()))->toThrow(AutocountUnreachable::class);
})->with(['createARAPContraEntry', 'updateARAPContraEntry']);

it('gets contra entries by DocNo and returns [] when none is found', function () {
    Http::fake(['*/ARAPContraEntry/GetARAPContraEntry' => Http::sequence()
        ->push([['DocNo' => 'JV-000016']])
        ->push(['Status' => 'Fail', 'Message' => 'Record not found'])]);

    expect(autocount()->getARAPContraEntries('JV-000016'))->toBe([['DocNo' => 'JV-000016']])
        ->and(autocount()->getARAPContraEntries(['JV-999999']))->toBe([]);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://autocount.test/api/ARAPContraEntry/GetARAPContraEntry'
        && $request['DocNo'] === ['JV-000016']);
});

it('rethrows a transport failure from get instead of returning []', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('timed out')]);

    expect(fn () => autocount()->getARAPContraEntries('JV-000016'))->toThrow(AutocountUnreachable::class);
});

it('deletes contra entries by DocNo', function () {
    Http::fake(['*/ARAPContraEntry/DeleteARAPContraEntry' => Http::response(['Status' => 'Success'])]);

    autocount()->deleteARAPContraEntries('JV-000016');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://autocount.test/api/ARAPContraEntry/DeleteARAPContraEntry'
        && $request->data() === ['DocNo' => ['JV-000016']]);
});

it('cancels contra entries by DocNo', function () {
    Http::fake(['*/ARAPContraEntry/CancelARAPContraEntry' => Http::response(['Status' => 'Success'])]);

    autocount()->cancelARAPContraEntries(['JV-000016', 'JV-000017']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->url() === 'https://autocount.test/api/ARAPContraEntry/CancelARAPContraEntry'
        && $request->data() === ['DocNo' => ['JV-000016', 'JV-000017'], 'Cancelled' => true]);
});
