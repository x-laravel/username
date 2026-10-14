<?php

namespace XLaravel\Username\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use XLaravel\Username\Concerns\HasUsername;

class HasUsernameTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->softDeletes();
        });
    }

    public function testGeneratesAUsernameFromTheSourceWhenEmpty(): void
    {
        $this->assertSame('ahmet-can', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testNumbersAGeneratedUsernameThatIsTaken(): void
    {
        Member::create(['name' => 'Ahmet Can']);

        $this->assertSame('ahmet-can1', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testFallsBackWhenTheSourceIsEmpty(): void
    {
        $this->assertSame('user1', Member::create(['name' => null])->username);
    }

    public function testLowercasesAGivenUsername(): void
    {
        $this->assertSame('ahmet.can', Member::create(['username' => 'Ahmet.Can'])->username);
    }

    public function testKeepsUppercaseWhenAllowed(): void
    {
        Config::set('username.rules.uppercase', true);

        $this->assertSame('Ahmet.Can', Member::create(['username' => 'Ahmet.Can'])->username);
    }

    public function testLeavesAnUnchangedUsernameAlone(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        Member::query()->whereKey($member->getKey())->update(['username' => 'Legacy']);

        Member::query()->whereKey($member->getKey())->firstOrFail()->update(['name' => 'Ahmet']);

        $this->assertSame('Legacy', Member::query()->whereKey($member->getKey())->value('username'));
    }

    public function testIgnoresItsOwnRowWhenCheckingAvailability(): void
    {
        $member = Member::create(['username' => 'ahmet']);

        $this->assertFalse($member->usernameTaken('ahmet'));
        $this->assertTrue((new Member)->usernameTaken('ahmet'));
    }

    public function testATrashedMembersUsernameIsNotGivenAgain(): void
    {
        Member::create(['name' => 'Ahmet Can'])->delete();

        $this->assertTrue((new Member)->usernameTaken('ahmet-can'));
        $this->assertSame('ahmet-can1', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testARestoredMemberKeepsItsUsername(): void
    {
        $member = Member::create(['name' => 'Ahmet Can']);
        $member->delete();
        $other = Member::create(['name' => 'Ahmet Can']);

        $member->restore();

        $this->assertSame('ahmet-can', Member::query()->whereKey($member->getKey())->value('username'));
        $this->assertSame('ahmet-can1', Member::query()->whereKey($other->getKey())->value('username'));
    }
}

/**
 * @property string $username
 * @property string|null $name
 */
class Member extends Model
{
    use HasUsername;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    public function usernameSource(): string
    {
        $name = $this->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}
