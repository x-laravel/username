<?php

namespace XLaravel\Username\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use XLaravel\Username\Format;
use XLaravel\Username\Username;

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
        });
    }

    public function usernameColumn(): string
    {
        return 'username';
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

    protected function prepareUsername(): void
    {
        $column = $this->usernameColumn();
        $value = $this->getAttribute($column);

        if (! is_string($value) || trim($value) === '') {
            $this->setAttribute($column, Username::generate($this->usernameSource(), $this->usernameTaken(...)));

            return;
        }

        if ($this->isDirty($column) && ! Format::fromConfig()->uppercase) {
            $this->setAttribute($column, Str::lower($value));
        }
    }
}
