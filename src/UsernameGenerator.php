<?php

namespace XLaravel\Username;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use XLaravel\Username\Rules\NotReserved;

final class UsernameGenerator
{
    public static function normalize(string $value, ?Format $format = null): string
    {
        $format ??= Format::fromConfig();
        $separator = $format->separator();

        $value = Str::ascii($value, 'tr');

        if (! $format->uppercase) {
            $value = strtolower($value);
        }

        $value = (string) preg_replace('/[^' . $format->allowedCharacters() . ']+/', $separator, $value);

        if ($separator !== '') {
            $value = (string) preg_replace('/' . preg_quote($separator, '/') . '{2,}/', $separator, $value);
        }

        $value = (string) preg_replace('/\.{2,}/', '.', $value);

        return trim(substr(trim($value, '.' . $separator), 0, $format->max), '.' . $separator);
    }

    /**
     * @param  callable(string): bool  $taken
     */
    public static function generate(string $value, callable $taken, ?Format $format = null): string
    {
        $format ??= Format::fromConfig();
        $base = self::normalize($value, $format);

        if ($base === '' || (! $format->numeric && ctype_digit($base))) {
            $base = self::normalize(Config::string('username.fallback', 'user') . $base, $format);
        }

        $candidate = $base;
        $separator = $format->separator();

        for ($suffix = 2; ! self::available($candidate, $taken, $format); $suffix++) {
            $ending = $separator . $suffix;
            $candidate = rtrim(substr($base, 0, $format->max - strlen($ending)), '.' . $separator) . $ending;
        }

        return $candidate;
    }

    /**
     * @param  callable(string): bool  $taken
     */
    private static function available(string $candidate, callable $taken, Format $format): bool
    {
        return $format->violation($candidate) === null
            && ! NotReserved::isReserved($candidate)
            && ! $taken($candidate);
    }
}
