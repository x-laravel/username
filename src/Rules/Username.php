<?php

namespace XLaravel\Username\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Lang;
use XLaravel\Username\Format;

class Username implements ValidationRule
{
    private Format $format;

    public function __construct()
    {
        $this->format = Format::fromConfig();
    }

    public static function make(): self
    {
        return new self;
    }

    public function min(int $min): self
    {
        $this->format = $this->format->with(min: $min);

        return $this;
    }

    public function max(int $max): self
    {
        $this->format = $this->format->with(max: $max);

        return $this;
    }

    public function dots(bool $allow = true): self
    {
        $this->format = $this->format->with(dots: $allow);

        return $this;
    }

    public function hyphens(bool $allow = true): self
    {
        $this->format = $this->format->with(hyphens: $allow);

        return $this;
    }

    public function underscores(bool $allow = true): self
    {
        $this->format = $this->format->with(underscores: $allow);

        return $this;
    }

    public function uppercase(bool $allow = true): self
    {
        $this->format = $this->format->with(uppercase: $allow);

        return $this;
    }

    public function numeric(bool $allow = true): self
    {
        $this->format = $this->format->with(numeric: $allow);

        return $this;
    }

    public function format(): Format
    {
        return $this->format;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $violation = $this->format->violation($value);

        if ($violation === null) {
            return;
        }

        $fail('username::validation.' . $violation)->translate([
            'min' => (string) $this->format->min,
            'max' => (string) $this->format->max,
            'characters' => $this->characters(),
        ]);
    }

    private function characters(): string
    {
        $parts = array_map($this->translate(...), array_keys(array_filter([
            $this->format->uppercase ? 'letters' : 'lowercase_letters' => true,
            'digits' => true,
            'hyphens' => $this->format->hyphens,
            'underscores' => $this->format->underscores,
            'dots_character' => $this->format->dots,
        ])));

        $last = array_pop($parts);

        return $parts === [] ? $last : implode(', ', $parts) . ' ' . $this->translate('and') . ' ' . $last;
    }

    private function translate(string $key): string
    {
        $line = Lang::get('username::validation.' . $key);

        return is_string($line) ? $line : $key;
    }
}
