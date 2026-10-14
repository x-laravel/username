# Username for Laravel

<p align="center">
<a href="https://github.com/X-Laravel/username/actions/workflows/tests.yml"><img src="https://github.com/X-Laravel/username/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
<a href="https://packagist.org/packages/x-laravel/username" rel="nofollow"><img src="https://img.shields.io/packagist/v/x-laravel/username" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/x-laravel/username" rel="nofollow"><img src="https://img.shields.io/packagist/php-v/x-laravel/username" alt="PHP Version"></a>
<a href="https://packagist.org/packages/x-laravel/username" rel="nofollow"><img src="https://img.shields.io/packagist/dt/x-laravel/username" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/x-laravel/username" rel="nofollow"><img src="https://img.shields.io/packagist/l/x-laravel/username" alt="License"></a>
</p>

Username handling for Laravel in one place: a configurable format rule, a reserved-name rule and a
generator that turns any text — a name, an e-mail prefix, a social nickname — into a free username
that passes the format rule.

## Requirements

| Package version | PHP | Laravel |
|-----------------|-----|---------|
| 1.x | 8.2+ | 12, 13 |

## Installation

```bash
composer require x-laravel/username
```

The service provider is registered through package discovery. Publish the config to change the
defaults:

```bash
php artisan vendor:publish --tag=username-config
```

## The format

Out of the box a username:

- contains only lowercase English letters, digits, hyphens (`-`), underscores (`_`) and dots (`.`);
- does not start or end with a dot and has no two dots in a row;
- is 2 to 30 characters long.

Every part of it is a config key in `config/username.php`:

| Key | Default | Meaning |
|-----|---------|---------|
| `rules.min` | `2` | Minimum length |
| `rules.max` | `30` | Maximum length |
| `rules.dots` | `true` | Allow dots (never at the edges or doubled) |
| `rules.hyphens` | `true` | Allow hyphens |
| `rules.underscores` | `true` | Allow underscores |
| `rules.uppercase` | `false` | Allow uppercase letters |
| `rules.numeric` | `true` | Allow usernames made of digits only |
| `fallback` | `'user'` | Base the generator uses when nothing usable is left of its input |
| `reserved` | see the file | Names the reserved-name rule rejects |

Letters outside the English alphabet are never allowed, so a username always fits in a URL without
encoding.

## Usage

### Format rule

```php
use XLaravel\Username\Rules\Username;

$request->validate([
    'username' => ['required', new Username, 'unique:users'],
]);
```

The rule reads the config; override any part for a single field:

```php
Username::make()->min(3)->max(20)->dots(false)->numeric(false)->uppercase();
```

With `uppercase` off the rule rejects capitals rather than lowering them. Lowercase the input
before validation if users may type capitals, for example in a form request:

```php
protected function prepareForValidation(): void
{
    $this->merge(['username' => strtolower((string) $this->input('username'))]);
}
```

The error message names exactly the characters that are allowed, so it stays correct when you change
the config.

### Reserved names

```php
use XLaravel\Username\Rules\NotReserved;

$request->validate([
    'username' => ['required', new Username, new NotReserved(['acme']), 'unique:users'],
]);
```

The list comes from the `reserved` config key; names passed to the rule are added to it. Matching
ignores case, dots, hyphens and underscores, so `ad.min` and `Ad-Min` count as `admin`.

### Generating a username

```php
use App\Models\User;
use XLaravel\Username\Username;

$username = Username::generate(
    Str::before($email, '@'),
    fn (string $candidate) => User::where('username', $candidate)->exists(),
);
```

`generate()` normalizes the text, then appends `1`, `2`, … until the candidate is long enough, not
reserved and not taken — trimming the base so the result never exceeds the maximum length. The
result always passes the format rule.

`normalize()` does the first half on its own: it transliterates to ASCII (Turkish letters such as
`ı`, `ş` and `ğ` included), lowercases unless uppercase is allowed, turns runs of disallowed
characters into a hyphen (an underscore when hyphens are off), collapses repeated dots, trims dots
and separators from the edges and cuts the result to the maximum length.

```php
Username::normalize('Iğdır Şükrü');      // igdir-sukru
Username::normalize('ahmet.can+promo');  // ahmet.can-promo
```

When digit-only usernames are not allowed, `generate()` puts the fallback in front of them, so
`12345` becomes `user12345`.

### Model trait

```php
use XLaravel\Username\Concerns\HasUsername;

class User extends Model
{
    use HasUsername;

    public function usernameSource(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
```

On every save the trait:

- generates a free username from `usernameSource()` when the column is empty;
- lowercases a username that was set or changed, unless uppercase is allowed.

A username that did not change is left alone, so existing rows are never rewritten behind your back.
Override `usernameColumn()` when the column is not called `username`. Availability is checked with
`usernameTaken()`, which ignores global scopes (soft-deleted rows still count) and the model's own
row; override it to scope the check differently.

### String aliases

```php
$request->validate([
    'username' => ['required', 'username', 'not_reserved_username'],
]);
```

Both aliases use the config. Laravel resolves their messages like any extended rule, so add lines to
your application's `lang/xx/validation.php`, or pass inline messages.

## Customising the messages

The rule objects come with Turkish and English messages. To change them, publish the language files:

```bash
php artisan vendor:publish --tag=username-translations
```

They land in `lang/vendor/username/{tr,en}/validation.php`.

## Testing

```bash
composer install
composer test
composer analyse
```

The suite runs on Orchestra Testbench against every supported Laravel and PHP combination in CI,
along with PHPStan (Larastan) at its highest level.

## License

Open source software licensed under the [MIT license](LICENSE).
