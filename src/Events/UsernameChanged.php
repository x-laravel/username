<?php

namespace XLaravel\Username\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

class UsernameChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly Model $model,
        public readonly string $old,
        public readonly string $new,
    ) {}
}
