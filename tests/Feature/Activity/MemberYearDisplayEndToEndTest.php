<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Activity;

use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Memberships\Infrastructure\Activity\MemberYearActivitySource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * With the core's own service, not a double: the association member role the module gives when it
 * activates a period is the one the profile page reads, so an account that shows the «Socio» badge
 * has facts, and takes them away the moment it hides it, announcing it to the core.
 */
final class MemberYearDisplayEndToEndTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15T12:00:00+02:00'));
        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        config()->set('modules.settings.memberships.enable_role_assignment', true);
    }

    public function test_the_role_the_module_gives_is_the_one_the_badge_needs_and_hiding_it_takes_the_years_away(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana Socia']);
        app(AuthorizationServiceInterface::class)->assignRole($user, 'member');
        UserProfileModel::query()->whereKey($user->id)->update([
            'visibility' => ProfileVisibility::Members->value,
            'show_membership' => true,
        ]);

        $member = MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2020-0001',
            'first_name' => 'Ana',
            'last_name' => 'García',
            'member_type' => 'regular',
            'status' => 'active',
            'user_id' => $user->id,
            'joined_at' => '2020-01-01 00:00:00',
        ]);
        // Activating the period is what gives the account the association role
        MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'rolling',
            'start_date' => '2025-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);

        $this->assertTrue($user->roles()->where('name', 'socio')->exists(), 'The module gives the association role.');
        $shown = $this->facts($user->id.':2025');
        $this->assertCount(1, $shown);
        $this->assertCount(1, $this->facts($user->id.':2026'));

        $changed = [];
        Event::listen(ProfileMembershipDisplayChanged::class, static function (ProfileMembershipDisplayChanged $event) use (&$changed): void {
            $changed[] = $event->userId;
        });
        $events = Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);

        UserProfileModel::query()->findOrFail($user->id)->update(['show_membership' => false]);

        $this->assertSame([$user->id], array_values(array_unique($changed)));

        $recorded = [];
        foreach ($events->dispatched(ActivityRecorded::class) as [$event]) {
            if ($event->sourceType === 'memberships.year') {
                $recorded[$event->subjectId] = true;
            }
        }
        $recorded = array_keys($recorded);
        sort($recorded);

        $this->assertSame([$user->id.':2025', $user->id.':2026'], $recorded);
        $this->assertSame([], $this->facts($user->id.':2025'));
        $this->assertSame([], $this->app->make(MemberYearActivitySource::class)->history(null, 50)->subjects);
    }

    /**
     * @return list<ActivityFactDTO>
     */
    private function facts(string $subjectId): array
    {
        // What the container remembered for a request is forgotten, as in a queued job
        $this->app->forgetScopedInstances();

        return $this->app->make(MemberYearActivitySource::class)->facts($subjectId);
    }
}
