<?php

namespace XLaravel\Username\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use XLaravel\Username\Events\UsernameChanged;
use XLaravel\Username\Format;
use XLaravel\Username\Models\UsernameHistory;
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

        static::created(function (Model $model) {
            /** @var Model&self $model */
            $model->releaseUsernameFromHistory();
        });

        static::saved(function (Model $model) {
            /** @var Model&self $model */
            $model->announceUsernameChange();
        });

        static::deleted(function (Model $model) {
            /** @var Model&self $model */
            $model->forgetUsernameHistoryWhenGone();
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
            ->exists()
            || $this->usernameIsReserved($candidate);
    }

    public function usernameIsReserved(string $username): bool
    {
        return $this->reservedUsernames()?->where('username', $username)->exists() ?? false;
    }

    public function keepsUsernameHistory(): bool
    {
        $history = property_exists($this, 'usernameHistory') ? $this->usernameHistory : null;

        return Config::boolean('username.history.enabled', true) && $history !== false;
    }

    public function usernameReservedFor(): ?int
    {
        if (property_exists($this, 'usernameReservedFor')) {
            return is_int($this->usernameReservedFor) ? $this->usernameReservedFor : null;
        }

        $days = Config::get('username.history.reserve_for', 90);

        return is_int($days) ? $days : null;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereUsername(Builder $query, string $username): void
    {
        $this->constrainToUsername($query, $username);
    }

    public static function findByUsername(string $username): ?static
    {
        /** @var static|null */
        return static::query()->whereUsername($username)->first();
    }

    /**
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Contracts\Database\Eloquent\Builder
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) !== $this->usernameColumn() || ! is_string($value)) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $builder = $query instanceof Model ? $query->newQuery() : $query;

        $this->constrainToUsername($builder, $value);

        return $builder;
    }

    /**
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $query
     */
    protected function constrainToUsername($query, string $username): void
    {
        $ids = $this->keepsUsernameHistory()
            ? $this->usernameHistory()->where('username', $username)->pluck('model_id')->all()
            : [];

        $query->where(function (Builder $query) use ($username, $ids) {
            $query->where($query->qualifyColumn($this->usernameColumn()), $username);

            if ($ids !== []) {
                $query->orWhereIn($query->qualifyColumn($this->getKeyName()), $ids);
            }
        });
    }

    /**
     * @return Builder<UsernameHistory>
     */
    protected function usernameHistory(): Builder
    {
        return UsernameHistory::query()->where('model_type', $this->getMorphClass());
    }

    /**
     * @return Builder<UsernameHistory>|null
     */
    protected function reservedUsernames(): ?Builder
    {
        $days = $this->usernameReservedFor();

        if (! $this->keepsUsernameHistory() || $days === 0) {
            return null;
        }

        return $this->usernameHistory()
            ->when($this->exists, fn (Builder $query) => $query->where('model_id', '!=', $this->getKey()))
            ->when($days !== null, fn (Builder $query) => $query->where('created_at', '>=', now()->subDays((int) $days)));
    }

    protected function releaseUsernameFromHistory(): void
    {
        $username = $this->getAttribute($this->usernameColumn());

        if ($this->keepsUsernameHistory() && is_string($username)) {
            $this->usernameHistory()->where('username', $username)->delete();
        }
    }

    protected function forgetUsernameHistoryWhenGone(): void
    {
        if (method_exists($this, 'isForceDeleting') && ! $this->isForceDeleting()) {
            return;
        }

        $this->usernameHistory()->where('model_id', $this->getKey())->delete();
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

        if (! $this->wasChanged($column) || ! is_string($new)) {
            return;
        }

        $this->releaseUsernameFromHistory();

        if (! is_string($old) || $old === '') {
            return;
        }

        if ($this->keepsUsernameHistory()) {
            $this->usernameHistory()->where('username', $old)->delete();

            UsernameHistory::query()->create([
                'model_type' => $this->getMorphClass(),
                'model_id' => $this->getKey(),
                'username' => $old,
                'created_at' => now(),
            ]);
        }

        $this->fireModelEvent('usernameChanged', false);

        Event::dispatch(new UsernameChanged($this, $old, $new));
    }
}
