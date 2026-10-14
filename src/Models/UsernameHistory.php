<?php

namespace XLaravel\Username\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property string $username
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class UsernameHistory extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return Config::string('username.history.table', 'username_history');
    }

    public function getConnectionName(): ?string
    {
        $connection = Config::get('username.history.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = Config::get('username.history.prune_after', 365);

        return is_int($days)
            ? static::query()->where('created_at', '<', now()->subDays($days))
            : static::query()->whereRaw('1 = 0');
    }
}
