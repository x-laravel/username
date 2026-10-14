<?php

namespace XLaravel\Username\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Config;

class NotReserved implements ValidationRule
{
    /**
     * @param  array<int, string>  $names
     */
    public function __construct(private readonly array $names = []) {}

    /**
     * @param  array<int, string>  $names
     */
    public static function isReserved(string $value, array $names = []): bool
    {
        $reserved = array_map(self::compact(...), [...self::configured(), ...$names]);

        return in_array(self::compact($value), $reserved, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isReserved($value, $this->names)) {
            $fail('username::validation.reserved')->translate();
        }
    }

    /**
     * @return array<int, string>
     */
    private static function configured(): array
    {
        return array_values(array_filter(Config::array('username.reserved', []), is_string(...)));
    }

    private static function compact(string $value): string
    {
        return strtolower(str_replace(['.', '-', '_'], '', $value));
    }
}
