<?php

namespace XLaravel\Username\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use XLaravel\Username\UsernameServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [UsernameServiceProvider::class];
    }
}
