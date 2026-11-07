<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Console;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The expiry warning is sent once per membership and expiry date, whatever the calendar year
 * the warning window falls in.
 */
final class ExpiringNoticeMarkTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_a_window_crossing_new_year_warns_only_once(): void
    {
        Event::fake([MembershipExpiring::class]);
        $membership = $this->membership('2027-01-15');

        $this->travelTo(CarbonImmutable::parse('2026-12-20 12:00:00'));
        $this->processExpired();
        $this->travelTo(CarbonImmutable::parse('2027-01-05 12:00:00'));
        $this->processExpired();

        Event::assertDispatchedTimes(MembershipExpiring::class, 1);
        $this->assertStringContainsString('[EXPIRING_NOTIFIED:2027-01-15]', (string) $membership->fresh()?->notes);
    }

    public function test_a_renewed_membership_is_warned_again_for_its_new_expiry(): void
    {
        Event::fake([MembershipExpiring::class]);
        $membership = $this->membership('2026-12-31');

        $this->travelTo(CarbonImmutable::parse('2026-12-10 12:00:00'));
        $this->processExpired();
        $membership->update(['end_date' => '2027-12-31']);
        $this->travelTo(CarbonImmutable::parse('2027-12-10 12:00:00'));
        $this->processExpired();

        Event::assertDispatchedTimes(MembershipExpiring::class, 2);
    }

    public function test_a_year_mark_saved_before_the_change_still_counts_for_the_same_window(): void
    {
        Event::fake([MembershipExpiring::class]);
        // Warned in December under the old per-year mark; the window now runs into January
        $this->membership('2027-01-15', '[EXPIRING_NOTIFIED:2026]');

        $this->travelTo(CarbonImmutable::parse('2027-01-05 12:00:00'));
        $this->processExpired();

        Event::assertNotDispatched(MembershipExpiring::class);
    }

    public function test_a_year_mark_from_before_the_window_does_not_hide_the_warning(): void
    {
        Event::fake([MembershipExpiring::class]);
        // Left by the previous expiry, a year earlier
        $this->membership('2027-01-15', '[EXPIRING_NOTIFIED:2025]');

        $this->travelTo(CarbonImmutable::parse('2026-12-20 12:00:00'));
        $this->processExpired();

        Event::assertDispatchedTimes(MembershipExpiring::class, 1);
    }

    public function test_the_mark_keeps_the_rest_of_the_notes(): void
    {
        Event::fake([MembershipExpiring::class]);
        $membership = $this->membership('2027-01-15', 'Paga en mano');

        $this->travelTo(CarbonImmutable::parse('2026-12-20 12:00:00'));
        $this->processExpired();

        $this->assertSame('Paga en mano [EXPIRING_NOTIFIED:2027-01-15]', $membership->fresh()?->notes);
    }

    private function processExpired(): void
    {
        $this->app->make(Kernel::class)->registerCommand($this->app->make(ProcessExpiredMemberships::class));

        $this->assertSame(0, $this->app->make(Kernel::class)->call('memberships:process-expired'));
    }

    private function membership(string $end, ?string $notes = null): MembershipModel
    {
        $member = MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'ana@example.test',
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => '2025-01-01',
        ]);

        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'rolling',
            'start_date' => '2026-01-16',
            'end_date' => $end,
            'status' => 'active',
            'notes' => $notes,
        ]);
    }
}
