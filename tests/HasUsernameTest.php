<?php

namespace XLaravel\Username\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use XLaravel\Username\Concerns\HasUsername;
use XLaravel\Username\Events\UsernameChanged;
use XLaravel\Username\Models\UsernameHistory;
use XLaravel\Username\Rules\UniqueUsername;

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

        $migration = require __DIR__ . '/../database/migrations/2026_10_14_000000_create_username_history_table.php';

        if (is_object($migration) && method_exists($migration, 'up')) {
            $migration->up();
        }

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

        public function testRecordsTheOldUsernameAndResolvesIt(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);

        $this->assertSame(['ahmet'], UsernameHistory::query()->pluck('username')->all());
        $this->assertTrue(Member::findByUsername('ahmet')?->is($member));
        $this->assertTrue(Member::findByUsername('ahmet.can')?->is($member));
        $this->assertNull(Member::findByUsername('baska'));
        $this->assertSame(1, Member::query()->whereUsername('ahmet')->whereKey($member->getKey())->count());
    }

    public function testRouteBindingFollowsTheHistory(): void
    {
        $member = new class extends Member {
            protected $table = 'members';

            public function getRouteKeyName(): string
            {
                return 'username';
            }
        };
        $member->fill(['username' => 'ahmet'])->save();
        $member->update(['username' => 'ahmet.can']);

        $this->assertTrue((new $member)->resolveRouteBinding('ahmet')?->is($member));
    }

    public function testAnOldUsernameIsReservedForItsOwner(): void
    {
        Member::create(['username' => 'ahmet'])->update(['username' => 'ahmet.can']);

        $this->assertTrue((new Member)->usernameTaken('ahmet'));
        $this->assertSame('ahmet-2', Member::create(['name' => 'Ahmet'])->username);
    }

    public function testTheReservationEndsAfterItsPeriod(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);

        $this->travel(91)->days();

        $other = Member::create(['name' => 'Ahmet']);

        $this->assertSame('ahmet', $other->username);
        $this->assertTrue(Member::findByUsername('ahmet')?->is($other));
        $this->assertSame(0, UsernameHistory::query()->where('username', 'ahmet')->count());
    }

    public function testANullReservationNeverEndsAndAZeroOneReservesNothing(): void
    {
        Config::set('username.history.reserve_for', null);
        Member::create(['username' => 'ahmet'])->update(['username' => 'ahmet.can']);
        $this->travel(5000)->days();
        $this->assertTrue((new Member)->usernameTaken('ahmet'));

        Config::set('username.history.reserve_for', 0);
        $this->assertFalse((new Member)->usernameTaken('ahmet'));
    }

    public function testTheModelCanSetItsOwnReservation(): void
    {
        $member = new class extends Member {
            protected $table = 'members';

            protected ?int $usernameReservedFor = 10;
        };

        $this->assertSame(10, $member->usernameReservedFor());
    }

    public function testAnOwnerCanReturnToAnOldUsername(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);

        $member->update(['username' => 'ahmet']);

        $this->assertSame(['ahmet.can'], UsernameHistory::query()->pluck('username')->all());
    }

    public function testHistoryCanBeTurnedOff(): void
    {
        Config::set('username.history.enabled', false);
        Member::create(['username' => 'ahmet'])->update(['username' => 'ahmet.can']);

        $this->assertSame(0, UsernameHistory::query()->count());
        $this->assertNull(Member::findByUsername('ahmet'));
        $this->assertFalse((new Member)->usernameTaken('ahmet'));
    }

    public function testHistoryCanBeTurnedOffOnTheModel(): void
    {
        $member = new class extends Member {
            protected $table = 'members';

            protected bool $usernameHistory = false;
        };

        $member->fill(['username' => 'ahmet'])->save();
        $member->update(['username' => 'ahmet.can']);

        $this->assertSame(0, UsernameHistory::query()->count());
    }

    public function testAForceDeletedMemberLeavesNoHistory(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);

        $member->delete();
        $this->assertSame(1, UsernameHistory::query()->count());

        $member->forceDelete();
        $this->assertSame(0, UsernameHistory::query()->count());
    }

    public function testOldHistoryIsPruned(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);
        $this->travel(366)->days();
        $member->update(['username' => 'ahmet.yeni']);

        $this->artisan('model:prune', ['--model' => [UsernameHistory::class]]);

        $this->assertSame(['ahmet.can'], UsernameHistory::query()->pluck('username')->all());
    }

    public function testThePackageSchedulesPruning(): void
    {
        $daily = $this->pruningEvent();
        $this->assertNotNull($daily);
        $this->assertSame('0 0 * * *', $daily->expression);

        Config::set('username.history.prune_schedule', '30 3 * * 1');
        $weekly = $this->pruningEvent();
        $this->assertNotNull($weekly);
        $this->assertSame('30 3 * * 1', $weekly->expression);

        Config::set('username.history.prune_schedule', null);
        $this->assertNull($this->pruningEvent());

        Config::set('username.history.prune_schedule', 'daily');
        Config::set('username.history.prune_after', null);
        $this->assertNull($this->pruningEvent());
    }

    public function testAnUnknownPruneFrequencyIsRejected(): void
    {
        Config::set('username.history.prune_schedule', 'sometimes');

        $this->expectException(InvalidArgumentException::class);

        $this->pruningEvent();
    }

    public function testUniqueUsernameSeesLiveAndReservedUsernames(): void
    {
        $member = Member::create(['username' => 'ahmet']);
        $member->update(['username' => 'ahmet.can']);
        \Illuminate\Support\Facades\App::setLocale('tr');

        $rule = ['username' => new UniqueUsername(Member::class)];

        $this->assertSame(
            'username başka bir hesap tarafından kullanılıyor.',
            Validator::make(['username' => 'Ahmet.Can'], $rule)->errors()->first('username'),
        );
        $this->assertTrue(Validator::make(['username' => 'ahmet'], $rule)->fails());
        $this->assertTrue(Validator::make(['username' => 'mehmet'], $rule)->passes());
        $this->assertTrue(Validator::make(['username' => 'ahmet'], ['username' => new UniqueUsername($member)])->passes());
    }

    private function pruningEvent(): ?\Illuminate\Console\Scheduling\Event
    {
        $app = $this->app;
        $this->assertNotNull($app);
        $app->forgetInstance(Schedule::class);

        foreach ($app->make(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'model:prune')) {
                return $event;
            }
        }

        return null;
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
