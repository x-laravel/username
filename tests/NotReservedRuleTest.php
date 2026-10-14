<?php

namespace XLaravel\Username\Tests;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use XLaravel\Username\Rules\NotReserved;

class NotReservedRuleTest extends TestCase
{
    #[DataProvider('reservedValues')]
    public function testRejectsReservedNames(string $value): void
    {
        $this->assertTrue($this->validate($value, new NotReserved)->fails());
    }

    public function testAcceptsOrdinaryNames(): void
    {
        $this->assertTrue($this->validate('ahmet', new NotReserved)->passes());
        $this->assertTrue($this->validate('administrators', new NotReserved)->passes());
    }

    public function testExtraNamesCanBePassedToTheRule(): void
    {
        $this->assertTrue($this->validate('aqtivite', new NotReserved(['aqtivite']))->fails());
    }

    public function testTheListCanBeChangedInTheConfig(): void
    {
        Config::set('username.reserved', ['aqtivite']);

        $this->assertTrue($this->validate('aqtivite', new NotReserved)->fails());
        $this->assertTrue($this->validate('admin', new NotReserved)->passes());
    }

    public function testMessageIsTranslatedInTurkish(): void
    {
        App::setLocale('tr');

        $this->assertSame('username olarak bu ad kullanılamaz.', $this->validate('admin', new NotReserved)->errors()->first('username'));
    }

    public function testNonStringValuesAreLeftToOtherRules(): void
    {
        $this->assertTrue($this->validate(['admin'], new NotReserved)->passes());
    }

    public function testStringAliasUsesTheConfiguredList(): void
    {
        $this->assertTrue(Validator::make(['username' => 'destek'], ['username' => 'not_reserved_username'])->fails());
        $this->assertTrue(Validator::make(['username' => 'ahmet'], ['username' => 'not_reserved_username'])->passes());
    }

    public function testStringAliasTakesExtraNames(): void
    {
        $rules = ['username' => 'not_reserved_username:aqtivite,beta'];

        $this->assertTrue(Validator::make(['username' => 'aqtivite'], $rules)->fails());
        $this->assertTrue(Validator::make(['username' => 'be.ta'], $rules)->fails());
        $this->assertTrue(Validator::make(['username' => 'admin'], $rules)->fails());
        $this->assertTrue(Validator::make(['username' => 'ahmet'], $rules)->passes());
    }

    /** @return array<string, array{string}> */
    public static function reservedValues(): array
    {
        return [
            'english' => ['admin'],
            'turkish' => ['destek'],
            'uppercase' => ['ADMIN'],
            'separated by dots' => ['ad.min'],
            'separated by hyphens and underscores' => ['ad-m_in'],
        ];
    }

    private function validate(mixed $value, NotReserved $rule): \Illuminate\Validation\Validator
    {
        return Validator::make(['username' => $value], ['username' => $rule]);
    }
}
