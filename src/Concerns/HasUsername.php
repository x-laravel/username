<?php

namespace XLaravel\Username\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use XLaravel\Username\Events\UsernameChanged;
use XLaravel\Username\Format;
use XLaravel\Username\UsernameGenerator;

/**
 * @mixin Model
 */
trait HasUsername
{
    public static function bootHasUsername(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&self $model */
            $model->prepareUsername();

            if ($model->usernameIsChanging() && $model->fireModelEvent('usernameChanging') === false) {
                return false;
            }
        });

        static::saved(function (Model $model) {
            /** @var Model&self $model */
            $model->announceUsernameChange();
        });
    }

    /**
     * @param  array{touch?: bool|null}  $options
     */
    public function save(array $options = []): bool
    {
        for ($attempt = 1; ; $attempt++) {
            $generating = ! $this->hasUsername();

            try {
                return $this->getConnection()->transactionLevel() > 0
                    ? $this->getConnection()->transaction(fn () => parent::save($options))
                    : parent::save($options);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $generating || $attempt >= 3 || ! str_contains($exception->getMessage(), $this->usernameColumn())) {
                    throw $exception;
                }

                $this->setAttribute($this->usernameColumn(), null);
            }
        }
    }

    public function initializeHasUsername(): void
    {
        $this->addObservableEvents(['usernameChanging', 'usernameChanged']);
    }

    /**
     * @param  \Illuminate\Events\QueuedClosure|callable|array<int, mixed>|class-string  $callback
     */
    public static function usernameChanging($callback): void
    {
        static::registerModelEvent('usernameChanging', $callback);
    }

    /**
     * @param  \Illuminate\Events\QueuedClosure|callable|array<int, mixed>|class-string  $callback
     */
    public static function usernameChanged($callback): void
    {
        static::registerModelEvent('usernameChanged', $callback);
    }

    public function usernameColumn(): string
    {
        $column = property_exists($this, 'usernameColumn') ? $this->usernameColumn : null;

        return is_string($column) && $column !== '' ? $column : 'username';
    }

    public function usernameSource(): string
    {
        return '';
    }

    public function usernameTaken(string $candidate): bool
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where($this->usernameColumn(), $candidate)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    protected function hasUsername(): bool
    {
        $value = $this->getAttribute($this->usernameColumn());

        return is_string($value) && trim($value) !== '';
    }

    protected function prepareUsername(): void
    {
        $column = $this->usernameColumn();
        $value = $this->getAttribute($column);

        if (! is_string($value) || ! $this->hasUsername()) {
            $this->setAttribute($column, UsernameGenerator::generate($this->usernameSource(), $this->usernameTaken(...)));

            return;
        }

        if ($this->isDirty($column) && ! Format::fromConfig()->uppercase) {
            $this->setAttribute($column, Str::lower($value));
        }
    }

    protected function usernameIsChanging(): bool
    {
        $old = $this->getRawOriginal($this->usernameColumn());

        return $this->exists && $this->isDirty($this->usernameColumn()) && is_string($old) && $old !== '';
    }

    protected function announceUsernameChange(): void
    {
        $column = $this->usernameColumn();
        $old = $this->getRawOriginal($column);
        $new = $this->getAttribute($column);

        if (! $this->wasChanged($column) || ! is_string($old) || $old === '' || ! is_string($new)) {
            return;
        }

        $this->fireModelEvent('usernameChanged', false);

        Event::dispatch(new UsernameChanged($this, $old, $new));
    }
}
