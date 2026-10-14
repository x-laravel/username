<?php

namespace XLaravel\Username;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use XLaravel\Username\Rules\NotReserved;

class UsernameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/username.php', 'username');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'username');

        $this->publishes([
            __DIR__ . '/../config/username.php' => $this->app->configPath('username.php'),
        ], 'username-config');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/username'),
        ], 'username-translations');

        Validator::extend('username', fn (string $attribute, mixed $value) => Format::fromConfig()->violation($value) === null);

        Validator::extend(
            'not_reserved_username',
            fn (string $attribute, mixed $value, array $parameters) => ! is_string($value)
                || ! NotReserved::isReserved($value, array_values(array_filter($parameters, is_string(...)))),
        );
    }
}
