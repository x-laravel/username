<?php

namespace XLaravel\Username\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use XLaravel\Username\Concerns\HasUsername;
use XLaravel\Username\Events\UsernameChanged;

class HasUsernameTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        MemberObserver::$heard = [];
        RacyMember::$misses = 0;

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->unique();
            $table->softDeletes();
        });

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('handle')->unique();
        });
    }

    public function testGeneratesAUsernameFromTheSourceWhenEmpty(): void
    {
        $this->assertSame('ahmet-can', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testNumbersAGeneratedUsernameThatIsTaken(): void
    {
        Member::create(['name' => 'Ahmet Can']);

        $this->assertSame('ahmet-can-2', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testFallsBackWhenTheSourceIsEmpty(): void
    {
        $this->assertSame('user-2', Member::create(['name' => null])->username);
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
        $this->assertSame('ahmet-can-2', Member::create(['name' => 'Ahmet Can'])->username);
    }

    public function testARestoredMemberKeepsItsUsername(): void
    {
        $member = Member::create(['name' => 'Ahmet Can']);
        $member->delete();
        $other = Member::create(['name' => 'Ahmet Can']);

        $member->restore();

        $this->assertSame('ahmet-can', Member::query()->whereKey($member->getKey())->value('username'));
        $this->assertSame('ahmet-can-2', Member::query()->whereKey($other->getKey())->value('username'));
    }

    public function testAnnouncesAChangedUsername(): void
    {
        $events = $this->recordEvents();
        $member = Member::create(['username' => 'ahmet']);

        $member->update(['username' => 'Ahmet.Can']);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(UsernameChanged::class, $event);
        $this->assertTrue($event->model->is($member));
        $this->assertSame('ahmet', $event->old);
        $this->assertSame('ahmet.can', $event->new);
    }

    public function testDoesNotAnnounceTheFirstUsername(): void
    {
        $events = $this->recordEvents();

        Member::create(['name' => 'Ahmet Can']);

        $this->assertCount(0, $events);
    }

    public function testDoesNotAnnounceWhenOtherAttributesChange(): void
    {
        $events = $this->recordEvents();
        $member = Member::create(['username' => 'ahmet']);

        $member->update(['name' => 'Ahmet']);
        $member->update(['username' => 'AHMET']);

        $this->assertCount(0, $events);
    }

    public function testAnnouncesOnlyAfterTheTransactionCommits(): void
    {
        $events = $this->recordEvents();
        $member = Member::create(['username' => 'ahmet']);

        DB::transaction(function () use ($member, $events) {
            $member->update(['username' => 'ahmet.can']);

            $this->assertCount(0, $events);
        });

        $this->assertCount(1, $events);
    }

    public function testDoesNotAnnounceARolledBackChange(): void
    {
        $events = $this->recordEvents();
        $member = Member::create(['username' => 'ahmet']);

        DB::beginTransaction();
        $member->update(['username' => 'ahmet.can']);
        DB::rollBack();

        $this->assertCount(0, $events);
    }

    public function testObserverHearsTheChangeWithTheOldUsernameStillReadable(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        Member::observe(MemberObserver::class);

        $member->update(['username' => 'ahmet.can']);

        $this->assertSame([
            'changing: ahmet -> ahmet.can',
            'changed: ahmet -> ahmet.can',
        ], MemberObserver::$heard);
    }

    public function testObserverIsNotCalledForTheFirstUsernameOrOtherChanges(): void
    {
        Member::observe(MemberObserver::class);

        $member = Member::create(['name' => 'Ahmet Can']);
        $member->update(['name' => 'Ahmet']);

        $this->assertSame([], MemberObserver::$heard);
    }

    public function testChangingCanCancelTheSave(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        Member::usernameChanging(fn () => false);

        $this->assertFalse($member->update(['username' => 'ahmet.can']));
        $this->assertSame('ahmet', Member::query()->whereKey($member->getKey())->value('username'));
    }

    public function testCallbacksCanBeRegisteredOnTheModel(): void
    {
        /** @var \ArrayObject<int, string> $heard */
        $heard = new \ArrayObject;
        Member::usernameChanged(function (Member $member) use ($heard) {
            $heard[] = $member->username;
        });

        Member::create(['username' => 'ahmet'])->update(['username' => 'ahmet.can']);

        $this->assertSame(['ahmet.can'], $heard->getArrayCopy());
    }

        public function testTheColumnCanBeSetWithAProperty(): void
    {
        $account = Account::create(['name' => 'Ahmet Can']);

        $this->assertSame('handle', $account->usernameColumn());
        $this->assertSame('ahmet-can', $account->handle);
        $this->assertSame('ahmet-can-2', Account::create(['name' => 'Ahmet Can'])->handle);
        $this->assertSame('ahmet.can', Account::create(['handle' => 'Ahmet.Can'])->handle);
    }

    public function testChangesToACustomColumnAreAnnounced(): void
    {
        $events = $this->recordEvents();
        $account = Account::create(['handle' => 'ahmet']);

        $account->update(['handle' => 'ahmet.can']);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf(UsernameChanged::class, $event);
        $this->assertSame('ahmet', $event->old);
        $this->assertSame('ahmet.can', $event->new);
    }

    public function testTheColumnDefaultsToUsername(): void
    {
        $this->assertSame('username', (new Member)->usernameColumn());
    }

        public function testRetriesWhenAGeneratedUsernameIsTakenMeanwhile(): void
    {
        Member::create(['name' => 'Ahmet Can']);
        RacyMember::$misses = 1;

        $this->assertSame('ahmet-can-2', RacyMember::create(['name' => 'Ahmet Can'])->username);
    }

    public function testGivesUpAfterThreeAttempts(): void
    {
        Member::create(['name' => 'Ahmet Can']);
        RacyMember::$misses = 3;

        $this->expectException(UniqueConstraintViolationException::class);

        RacyMember::create(['name' => 'Ahmet Can']);
    }

    public function testDoesNotRetryAUsernameThatWasGiven(): void
    {
        Member::create(['username' => 'ahmet']);

        $this->expectException(UniqueConstraintViolationException::class);

        Member::create(['username' => 'ahmet']);
    }

    public function testARetryKeepsTheSurroundingTransactionUsable(): void
    {
        Member::create(['name' => 'Ahmet Can']);
        RacyMember::$misses = 1;

        DB::transaction(function () {
            RacyMember::create(['name' => 'Ahmet Can']);
            Member::create(['username' => 'mehmet']);
        });

        $this->assertSame(['ahmet-can', 'ahmet-can-2', 'mehmet'], Member::query()->orderBy('id')->pluck('username')->all());
    }

        /**
     * @return \ArrayObject<int, UsernameChanged>
     */
    private function recordEvents(): \ArrayObject
    {
        /** @var \ArrayObject<int, UsernameChanged> $events */
        $events = new \ArrayObject;

        Event::listen(UsernameChanged::class, function (UsernameChanged $event) use ($events) {
            $events[] = $event;
        });

        return $events;
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

class RacyMember extends Member
{
    public static int $misses = 0;

    protected $table = 'members';

    public function usernameTaken(string $candidate): bool
    {
        if (self::$misses > 0) {
            self::$misses--;

            return false;
        }

        return parent::usernameTaken($candidate);
    }
}

/**
 * @property string $handle
 * @property string|null $name
 */
class Account extends Model
{
    use HasUsername;

    public $timestamps = false;

    protected $guarded = [];

    protected string $usernameColumn = 'handle';

    public function usernameSource(): string
    {
        $name = $this->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}

class MemberObserver
{
    /** @var array<int, string> */
    public static array $heard = [];

    public function usernameChanging(Member $member): void
    {
        self::$heard[] = 'changing: ' . self::original($member) . ' -> ' . $member->username;
    }

    public function usernameChanged(Member $member): void
    {
        self::$heard[] = 'changed: ' . self::original($member) . ' -> ' . $member->username;
    }

    private static function original(Member $member): string
    {
        $username = $member->getOriginal('username');

        return is_string($username) ? $username : '';
    }
}
