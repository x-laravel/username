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
php artisan vendor:publish --tag=username-migrations
php artisan migrate
```

The migration creates the username history table. The service provider is registered through
package discovery. Publish the config to change the
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
| `history.enabled` | `true` | Keep and resolve old usernames |
| `history.connection` | `null` | Connection of the history table |
| `history.table` | `'username_history'` | Name of the history table |
| `history.reserve_for` | `90` | Days an old username stays reserved for its owner |
| `history.prune_after` | `365` | Days after which history rows are pruned |
| `history.prune_schedule` | `'daily'` | Frequency or cron expression of the pruning job; `null` to schedule it yourself |

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
use Illuminate\Support\Str;
use XLaravel\Username\UsernameGenerator;

$username = UsernameGenerator::generate(
    Str::before($email, '@'),
    fn (string $candidate) => User::where('username', $candidate)->exists(),
);
```

`generate()` normalizes the text, then appends `-2`, `-3`, … until the candidate is long enough, not
reserved and not taken — trimming the base so the result never exceeds the maximum length. The
number is joined with a hyphen, or with an underscore when hyphens are off. The result always passes
the format rule.

`normalize()` does the first half on its own: it transliterates to ASCII (Turkish letters such as
`ı`, `ş` and `ğ` included), lowercases unless uppercase is allowed, turns runs of disallowed
characters into a hyphen (an underscore when hyphens are off), collapses repeated dots, trims dots
and separators from the edges and cuts the result to the maximum length.

```php
UsernameGenerator::normalize('Iğdır Şükrü');      // igdir-sukru
UsernameGenerator::normalize('ahmet.can+promo');  // ahmet.can-promo
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

The trait works on the `username` column. To use another one, set a property on the model:

```php
protected string $usernameColumn = 'handle';
```

Override `usernameColumn()` instead when the column has to be decided at runtime.

Availability is checked with `usernameTaken()`, which ignores global scopes (soft-deleted rows still
count) and the model's own row; override it to scope the check differently.

Two requests can pick the same free username at the same moment. When the trait generated the
username and the insert then fails on the username's unique index, it generates a new one and tries
again, up to three times. A username that was given rather than generated is never retried — the
`unique` validation rule is the place for that. Inside an open transaction the insert runs in a
savepoint, so a failed attempt does not break the surrounding transaction. The retry lives in the
trait's `save()` method; if your model overrides `save()` itself, call `parent::save()` from it to
keep the retry.

### Username history

When a username changes, the old one is written to the history table, so old profile links keep
finding the account and nobody else can take the name right away. Looking an account up by
username checks both the current username and the history:

```php
$user = User::findByUsername($username);

$user = User::query()->whereUsername($username)->firstOrFail();
```

Route model binding follows the history too when the route key is the username column.

An old username stays reserved for its owner for `reserve_for` days: no other account can take it,
and the generator skips it. The owner can always go back to it. When the period ends another
account may take the name; the history row is then removed and the name belongs to the new account.
A model can override the period with a `$usernameReservedFor` property, or opt out of the history
with `$usernameHistory = false`.

Rows older than `prune_after` days are deleted by Laravel's `model:prune` command, which the package
schedules daily for the history table; change it with `history.prune_schedule`. Force-deleting an
account removes its history; soft-deleting keeps it.

Laravel's `unique` rule cannot see the history table. Use `UniqueUsername`, which checks both live
and reserved usernames and ignores the given account's own names:

```php
use XLaravel\Username\Rules\UniqueUsername;

$request->validate([
    'username' => ['required', new Username, new UniqueUsername($request->user())],
]);
```

Pass the class name instead of an instance for a new account.

### Reacting to a changed username

When a saved username changes, the trait dispatches `XLaravel\Username\Events\UsernameChanged` with
the model and the old and new usernames — handy for redirecting old profile links, clearing caches
or writing an audit entry:

```php
use XLaravel\Username\Events\UsernameChanged;

Event::listen(function (UsernameChanged $event) {
    ProfileRedirect::create(['from' => $event->old, 'to' => $event->new]);
});
```

The event is dispatched only after the surrounding database transaction commits, so a rolled-back
change is never announced. Giving a new model its first username does not count as a change.

The trait also adds two model events, so the change can be handled in an observer:

```php
class UserObserver
{
    public function usernameChanging(User $user): bool
    {
        return ! $user->isLocked();
    }

    public function usernameChanged(User $user): void
    {
        $old = $user->getOriginal('username');
    }
}
```

- `usernameChanging` runs before the row is written. Return `false` to cancel the save.
- `usernameChanged` runs right after the row is written, inside the transaction if there is one.
  `getOriginal()` still returns the old username at that point.

Both can also be registered on the model, like any Eloquent event:

```php
User::usernameChanged(fn (User $user) => Cache::forget("profile:{$user->getOriginal('username')}"));
```

Use the observer events for work that belongs with the save, and the `UsernameChanged` event for work
that must only happen once the change is committed.

### String aliases

```php
$request->validate([
    'username' => ['required', 'username', 'not_reserved_username'],
]);
```

Both aliases use the config. `not_reserved_username` also takes extra names as parameters:
`not_reserved_username:acme,beta`. Laravel resolves the aliases' messages like any extended rule, so
add lines to your application's `lang/xx/validation.php`, or pass inline messages.

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
