<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\MembershipsServiceProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The profile's member tab (memberships#79): only for an account linked to a member record,
 * read only, computed on the profile page alone.
 */
final class ProfileMembershipTabTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private UserModel $user;

    protected function setUp(): void
    {
        parent::setUp();

        app('router')->getRoutes()->refreshNameLookups();
        $this->user = UserModel::factory()->create();
    }

    public function test_the_tab_is_hidden_for_an_account_without_member_record(): void
    {
        $this->actingAs($this->user)
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileMembershipVisible', false)
                ->where('profileMembership', null)
                ->where('profileMembershipOverdue', null));
    }

    public function test_a_linked_account_sees_its_situation_with_overdue_fees_as_badge(): void
    {
        $member = $this->member($this->user->id);
        $membership = $this->membership($member);
        $this->fee($membership, CalendarDate::today()->modify('-5 days')->format('Y-m-d'));
        $this->fee($membership, CalendarDate::today()->modify('+5 days')->format('Y-m-d'));

        $this->actingAs($this->user)
            ->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileMembershipVisible', true)
                ->where('profileMembershipOverdue', 1)
                ->where('profileMembership.memberNumber', '2026-0042')
                ->where('profileMembership.membership.status', 'active')
                ->has('profileMembership.pendingFees', 2)
                ->where('profileMembership.pendingFees.0.overdue', true));
    }

    public function test_without_overdue_fees_the_badge_counts_none(): void
    {
        $this->member($this->user->id);

        $this->actingAs($this->user)
            ->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileMembershipVisible', true)
                ->where('profileMembershipOverdue', 0));
    }

    public function test_another_account_never_sees_someone_elses_record(): void
    {
        $this->member($this->user->id);
        $other = UserModel::factory()->create();

        $this->actingAs($other)
            ->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileMembershipVisible', false)
                ->where('profileMembership', null));
    }

    public function test_the_situation_is_not_shared_outside_the_profile(): void
    {
        $this->member($this->user->id);

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('profileMembership', null)
                ->where('profileMembershipVisible', null));
    }

    public function test_the_slot_registers_the_tab_with_its_keys(): void
    {
        $provider = app()->getProvider(MembershipsServiceProvider::class);
        $this->assertInstanceOf(MembershipsServiceProvider::class, $provider);

        $slots = $provider->registerSlots();
        $this->assertCount(1, $slots);
        $slot = $slots[0];

        $this->assertSame('profile-sections', $slot->slot);
        $this->assertSame('components/profile/ProfileMembershipSection.vue', $slot->component);
        $this->assertSame(['profileMembership', 'profileMembershipOverdue', 'profileMembershipVisible'], $slot->dataKeys);
        $this->assertSame([
            'tabId' => MembershipsServiceProvider::PROFILE_TAB,
            'icon' => 'id-card',
            'labelKey' => 'memberships.profile.tabLabel',
            'badgeKey' => 'profileMembershipOverdue',
            'visibleKey' => 'profileMembershipVisible',
        ], $slot->profileTab);
    }

    private function member(?string $userId): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0042',
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'user_id' => $userId,
            'joined_at' => now(),
        ]);
    }

    private function membership(MemberModel $member): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'calendar_year',
            'start_date' => now()->startOfYear(),
            'end_date' => now()->endOfYear(),
            'status' => 'active',
        ]);
    }

    private function fee(MembershipModel $membership, string $dueOn): MembershipFeeModel
    {
        return MembershipFeeModel::create([
            'id' => (string) Str::uuid(),
            'membership_id' => $membership->id,
            'amount' => '30.00',
            'currency' => 'EUR',
            'due_date' => $dueOn,
        ]);
    }
}
