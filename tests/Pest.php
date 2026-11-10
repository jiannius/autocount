<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Jiannius\Autocount\Autocount;
use Jiannius\Autocount\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

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
