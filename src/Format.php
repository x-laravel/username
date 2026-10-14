<?php

namespace XLaravel\Username;

use Illuminate\Support\Facades\Config;

final class Format
{
    public function __construct(
        public readonly int $min = 2,
        public readonly int $max = 30,
        public readonly bool $dots = true,
        public readonly bool $hyphens = true,
        public readonly bool $underscores = true,
        public readonly bool $uppercase = false,
        public readonly bool $numeric = true,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            min: Config::integer('username.rules.min', 2),
            max: Config::integer('username.rules.max', 30),
            dots: Config::boolean('username.rules.dots', true),
            hyphens: Config::boolean('username.rules.hyphens', true),
            underscores: Config::boolean('username.rules.underscores', true),
            uppercase: Config::boolean('username.rules.uppercase', false),
            numeric: Config::boolean('username.rules.numeric', true),
        );
    }

    public function with(
        ?int $min = null,
        ?int $max = null,
        ?bool $dots = null,
        ?bool $hyphens = null,
        ?bool $underscores = null,
        ?bool $uppercase = null,
        ?bool $numeric = null,
    ): self {
        return new self(
            min: $min ?? $this->min,
            max: $max ?? $this->max,
            dots: $dots ?? $this->dots,
            hyphens: $hyphens ?? $this->hyphens,
            underscores: $underscores ?? $this->underscores,
            uppercase: $uppercase ?? $this->uppercase,
            numeric: $numeric ?? $this->numeric,
        );
    }

    /**
     * @return 'characters'|'dots'|'numeric'|'min'|'max'|null
     */
    public function violation(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A[' . $this->allowedCharacters() . ']+\z/', $value) !== 1) {
            return 'characters';
        }

        if ($this->dots && (str_starts_with($value, '.') || str_ends_with($value, '.') || str_contains($value, '..'))) {
            return 'dots';
        }

        if (! $this->numeric && ctype_digit($value)) {
            return 'numeric';
        }

        if (strlen($value) < $this->min) {
            return 'min';
        }

        if (strlen($value) > $this->max) {
            return 'max';
        }

        return null;
    }

    public function allowedCharacters(): string
    {
        return ($this->uppercase ? 'A-Za-z' : 'a-z')
            . '0-9'
            . ($this->underscores ? '_' : '')
            . ($this->hyphens ? '\-' : '')
            . ($this->dots ? '.' : '');
    }

    public function separator(): string
    {
        return match (true) {
            $this->hyphens => '-',
            $this->underscores => '_',
            default => '',
        };
    }
}
