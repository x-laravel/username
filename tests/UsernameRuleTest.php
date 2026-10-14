<?php

namespace XLaravel\Username\Tests;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use XLaravel\Username\Rules\Username;

class UsernameRuleTest extends TestCase
{
    #[DataProvider('validValues')]
    public function testAcceptsValidUsernames(string $value): void
    {
        $this->assertTrue($this->validate($value, new Username)->passes());
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidUsernames(mixed $value, string $violation): void
    {
        $validator = $this->validate($value, new Username);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            trans('username::validation.' . $violation, ['attribute' => 'username', 'min' => 2, 'max' => 30, 'characters' => $this->defaultCharacters()]),
            $validator->errors()->first('username'),
        );
    }

    public function testListsTheAllowedCharactersInTurkish(): void
    {
        App::setLocale('tr');

        $this->assertSame(
            'username yalnızca küçük İngilizce harf, rakam, tire (-), alt çizgi (_) ve nokta (.) içerebilir.',
            $this->validate('Ahmet', new Username)->errors()->first('username'),
        );
    }

    public function testListsOnlyTheCharactersThatAreAllowed(): void
    {
        $rule = Username::make()->dots(false)->hyphens(false)->uppercase();

        $this->assertSame(
            'The username field may only contain letters, numbers and underscores (_).',
            $this->validate('ahmet.can', $rule)->errors()->first('username'),
        );
    }

    public function testUppercaseIsRejectedByDefault(): void
    {
        $this->assertTrue($this->validate('Ahmet', new Username)->fails());
    }

    public function testUppercaseCanBeAllowedInTheConfig(): void
    {
        Config::set('username.rules.uppercase', true);

        $this->assertTrue($this->validate('Ahmet', new Username)->passes());
    }

    public function testUppercaseCanBeAllowedOnTheRule(): void
    {
        $this->assertTrue($this->validate('Ahmet', Username::make()->uppercase())->passes());
    }

    public function testDotsCanBeDisallowed(): void
    {
        Config::set('username.rules.dots', false);

        $this->assertTrue($this->validate('ahmet.can', new Username)->fails());
    }

    public function testHyphensAndUnderscoresCanBeDisallowed(): void
    {
        $rule = Username::make()->hyphens(false)->underscores(false);

        $this->assertTrue($this->validate('ahmet-can', $rule)->fails());
        $this->assertTrue($this->validate('ahmet_can', $rule)->fails());
    }

    public function testDigitOnlyUsernamesAreAllowedByDefault(): void
    {
        $this->assertTrue($this->validate('12345', new Username)->passes());
    }

    public function testDigitOnlyUsernamesCanBeDisallowedInTheConfig(): void
    {
        Config::set('username.rules.numeric', false);
        App::setLocale('tr');

        $this->assertSame(
            'username yalnızca rakamlardan oluşamaz.',
            $this->validate('12345', new Username)->errors()->first('username'),
        );
        $this->assertTrue($this->validate('1984ahmet', new Username)->passes());
    }

    public function testDigitOnlyUsernamesCanBeDisallowedOnTheRule(): void
    {
        $this->assertTrue($this->validate('12345', Username::make()->numeric(false))->fails());
    }

    public function testLengthLimitsComeFromTheConfig(): void
    {
        Config::set('username.rules.min', 5);
        Config::set('username.rules.max', 6);

        $this->assertTrue($this->validate('abcd', new Username)->fails());
        $this->assertTrue($this->validate('abcde', new Username)->passes());
        $this->assertTrue($this->validate('abcdefg', new Username)->fails());
    }

    public function testLengthLimitsCanBeSetOnTheRule(): void
    {
        $this->assertSame(
            'The username field must be at least 4 characters.',
            $this->validate('abc', Username::make()->min(4))->errors()->first('username'),
        );
    }

    public function testStringAliasFollowsTheConfig(): void
    {
        $this->assertTrue(Validator::make(['username' => 'ahmet.can'], ['username' => 'username'])->passes());
        $this->assertTrue(Validator::make(['username' => 'ahmet..can'], ['username' => 'username'])->fails());
    }

    public function testStringAliasSkipsNullableEmptyValues(): void
    {
        $this->assertTrue(Validator::make(['username' => null], ['username' => ['nullable', 'username']])->passes());
    }

    /** @return array<string, array{string}> */
    public static function validValues(): array
    {
        return [
            'letters' => ['ahmet'],
            'digits first' => ['1984ahmet'],
            'dot' => ['ahmet.can'],
            'several dots' => ['a.b.c'],
            'hyphen' => ['ahmet-can'],
            'underscore' => ['ahmet_can'],
            'separators at the edges' => ['_ahmet-'],
            'minimum length' => ['ab'],
            'maximum length' => [str_repeat('a', 30)],
        ];
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidValues(): array
    {
        return [
            'turkish letter' => ['ırem', 'characters'],
            'uppercase' => ['Ahmet', 'characters'],
            'space' => ['ahmet can', 'characters'],
            'at sign' => ['ahmet@can', 'characters'],
            'plus' => ['ahmet+can', 'characters'],
            'array' => [['ahmet'], 'characters'],
            'integer' => [12345, 'characters'],
            'leading dot' => ['.ahmet', 'dots'],
            'trailing dot' => ['ahmet.', 'dots'],
            'consecutive dots' => ['ahmet..can', 'dots'],
            'too short' => ['a', 'min'],
            'too long' => [str_repeat('a', 31), 'max'],
        ];
    }

    private function validate(mixed $value, Username $rule): \Illuminate\Validation\Validator
    {
        return Validator::make(['username' => $value], ['username' => $rule]);
    }

    private function defaultCharacters(): string
    {
        return 'lowercase letters, numbers, hyphens (-), underscores (_) and dots (.)';
    }
}
