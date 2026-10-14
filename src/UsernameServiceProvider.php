<?php

namespace XLaravel\Username;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use XLaravel\Username\Models\UsernameHistory;
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

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'username-migrations');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/username'),
        ], 'username-translations');

        Validator::extend('username', fn (string $attribute, mixed $value) => Format::fromConfig()->violation($value) === null);

        Validator::extend(
            'not_reserved_username',
            fn (string $attribute, mixed $value, array $parameters) => ! is_string($value)
                || ! NotReserved::isReserved($value, array_values(array_filter($parameters, is_string(...)))),
        );

        $this->callAfterResolving(Schedule::class, $this->schedulePruning(...));
    }

    private function schedulePruning(Schedule $schedule): void
    {
        $frequency = Config::get('username.history.prune_schedule', 'daily');

        if (! is_string($frequency) || $frequency === ''
            || ! Config::boolean('username.history.enabled', true)
            || Config::get('username.history.prune_after', 365) === null) {
            return;
        }

        $event = $schedule->command('model:prune', ['--model' => [UsernameHistory::class]]);

        if (str_contains($frequency, ' ')) {
            $event->cron($frequency);

            return;
        }

        if (! method_exists($event, $frequency)) {
            throw new InvalidArgumentException("[{$frequency}] is not a schedule frequency.");
        }

        $event->{$frequency}();
    }
}
