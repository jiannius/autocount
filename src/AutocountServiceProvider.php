<?php

namespace Jiannius\Autocount;

use Illuminate\Support\ServiceProvider;

class AutocountServiceProvider extends ServiceProvider
{
    // register
    public function register() : void
    {
        //
    }

    // boot
    public function boot() : void
    {
        $this->app->bind('autocount', fn($app) => new \Jiannius\Autocount\Autocount());
    }
}