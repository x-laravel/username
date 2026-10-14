<?php

namespace XLaravel\Username\Tests;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use XLaravel\Username\Format;
use XLaravel\Username\UsernameGenerator;

class UsernameGeneratorTest extends TestCase
{
    #[DataProvider('normalizedValues')]
    public function testNormalizesIntoAValidUsername(string $value, string $expected): void
    {
        $this->assertSame($expected, UsernameGenerator::normalize($value));
    }

    public function testNormalizationKeepsUppercaseWhenAllowed(): void
    {
        Config::set('username.rules.uppercase', true);

        $this->assertSame('Ahmet-Can', UsernameGenerator::normalize('Ahmet Can'));
    }

    public function testNormalizationUsesUnderscoresWhenHyphensAreOff(): void
    {
        $this->assertSame('ahmet_can', UsernameGenerator::normalize('Ahmet Can', new Format(hyphens: false)));
    }

    public function testNormalizationReplacesDotsWhenTheyAreOff(): void
    {
        $this->assertSame('ahmet-can', UsernameGenerator::normalize('ahmet.can', new Format(dots: false)));
    }

    public function testGenerateReturnsTheNormalizedValueWhenFree(): void
    {
        $this->assertSame('ahmet.can', UsernameGenerator::generate('Ahmet.Can', fn () => false));
    }

    public function testGenerateAppendsANumberWhenTaken(): void
    {
        $taken = ['ahmet', 'ahmet1'];

        $this->assertSame('ahmet2', UsernameGenerator::generate('ahmet', fn (string $name) => in_array($name, $taken, true)));
    }

    public function testGeneratePadsAShortValue(): void
    {
        $this->assertSame('s1', UsernameGenerator::generate('s', fn () => false));
    }

    public function testGenerateStaysWithinTheMaximumLength(): void
    {
        $value = str_repeat('a', 30);

        $generated = UsernameGenerator::generate($value, fn (string $name) => $name === $value);

        $this->assertSame(str_repeat('a', 29) . '1', $generated);
    }

    public function testGenerateSkipsReservedNames(): void
    {
        $this->assertSame('admin1', UsernameGenerator::generate('Admin', fn () => false));
    }

    public function testGenerateKeepsDigitOnlyValuesByDefault(): void
    {
        $this->assertSame('12345', UsernameGenerator::generate('12345', fn () => false));
    }

    public function testGeneratePrefixesDigitOnlyValuesWhenTheyAreNotAllowed(): void
    {
        Config::set('username.rules.numeric', false);

        $this->assertSame('user12345', UsernameGenerator::generate('12345', fn () => false));
    }

    public function testGenerateFallsBackWhenNothingUsableIsLeft(): void
    {
        Config::set('username.fallback', 'member');

        $this->assertSame('member', UsernameGenerator::generate('@@@', fn () => false));
    }

    public function testGeneratedUsernamesPassTheRule(): void
    {
        foreach (['Iğdır Şükrü', '.ahmet..can.', 'ırem', 'x', '+++', str_repeat('ş', 40)] as $value) {
            $this->assertNull(Format::fromConfig()->violation(UsernameGenerator::generate($value, fn () => false)), $value);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function normalizedValues(): array
    {
        return [
            'turkish letters' => ['Iğdır Şükrü Çağlayan', 'igdir-sukru-caglayan'],
            'dotless i' => ['ırem', 'irem'],
            'email local part' => ['ahmet.can+promo', 'ahmet.can-promo'],
            'repeated dots' => ['ahmet...can', 'ahmet.can'],
            'edge dots' => ['.ahmet.', 'ahmet'],
            'repeated separators' => ['ahmet   can', 'ahmet-can'],
            'symbols only' => ['@@@', ''],
            'truncated' => [str_repeat('a', 35), str_repeat('a', 30)],
            'no dot left at the cut' => [str_repeat('a', 29) . '.bc', str_repeat('a', 29)],
        ];
    }
}
