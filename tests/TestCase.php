<?php

namespace Jiannius\Autocount\Tests;

use Jiannius\Autocount\AutocountServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app) : array
    {
        return [AutocountServiceProvider::class];
    }
}
