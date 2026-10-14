<?php

namespace XLaravel\Username\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use XLaravel\Username\Concerns\HasUsername;

class UniqueUsername implements ValidationRule
{
    /**
     * @param  class-string<Model>|Model  $model
     */
    public function __construct(private string|Model $model) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $model = is_string($this->model) ? new $this->model : $this->model;

        if (! in_array(HasUsername::class, class_uses_recursive($model), true) || ! method_exists($model, 'usernameTaken')) {
            throw new LogicException(sprintf('[%s] does not use the HasUsername trait.', $model::class));
        }

        if ($model->usernameTaken(mb_strtolower($value))) {
            $fail('username::validation.taken')->translate();
        }
    }
}
