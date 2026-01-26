<?php

declare(strict_types=1);

namespace Modules\Memberships\Console\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Events\FeePaymentOverdue;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;

final class ProcessExpiredMemberships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memberships:process-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired memberships and send notifications';

    public function __construct(
        private readonly MembershipRepositoryInterface $membershipRepository,
        private readonly MembershipFeeRepositoryInterface $membershipFeeRepository,
        private readonly Dispatcher $eventDispatcher,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Processing expired memberships...');

        $expiredCount = $this->processExpiredMemberships();
        $expiringCount = $this->processExpiringMemberships();
        $overdueCount = $this->processOverdueFees();

        $this->newLine();
        $this->components->info("Summary:");
        $this->components->bulletList([
            "Memberships marked as expired: {$expiredCount}",
            "Expiring notifications sent: {$expiringCount}",
            "Overdue fee events dispatched: {$overdueCount}",
        ]);

        return self::SUCCESS;
    }

    /**
     * Find and mark expired memberships.
     */
    private function processExpiredMemberships(): int
    {
        $this->components->task('Processing expired memberships', function () use (&$count): void {
            $count = 0;
            $today = new DateTimeImmutable('today');

            // Get all active memberships that have expired (end_date < today)
            $memberships = $this->membershipRepository->findActiveExpiredBefore($today);

            foreach ($memberships as $membership) {
                // Mark as expired
                $membership->expire();
                $this->membershipRepository->save($membership);

                // Dispatch expired event
                $this->eventDispatcher->dispatch(MembershipExpired::create($membership));

                $count++;
            }
        });

        return $count ?? 0;
    }

    /**
     * Find memberships expiring soon and send notifications.
     */
    private function processExpiringMemberships(): int
    {
        $this->components->task('Processing expiring memberships', function () use (&$count): void {
            $count = 0;
            $warningDays = (int) config('memberships.expiration_warning_days', 30);
            $today = new DateTimeImmutable('today');

            $expiringMemberships = $this->membershipRepository->getExpiringMemberships($warningDays);

            foreach ($expiringMemberships as $membership) {
                // Check if we've already notified (check notes field for notification marker)
                if ($this->hasBeenNotified($membership)) {
                    continue;
                }

                // Calculate days until expiration
                $daysUntilExpiration = $this->calculateDaysUntilExpiration($membership->endDate, $today);

                // Dispatch expiring event
                $this->eventDispatcher->dispatch(
                    MembershipExpiring::create($membership, $daysUntilExpiration)
                );

                // Mark as notified by updating notes
                $this->markAsNotified($membership);

                $count++;
            }
        });

        return $count ?? 0;
    }

    /**
     * Find overdue fees and dispatch events.
     */
    private function processOverdueFees(): int
    {
        $this->components->task('Processing overdue fees', function () use (&$count): void {
            $count = 0;

            $overdueFees = $this->membershipFeeRepository->getOverdueFees();

            foreach ($overdueFees as $fee) {
                // Check if we've already dispatched an overdue event for this fee
                if ($this->hasOverdueEventDispatched($fee)) {
                    continue;
                }

                // Dispatch overdue event
                $this->eventDispatcher->dispatch(FeePaymentOverdue::create($fee));

                // Mark as notified
                $this->markFeeOverdueNotified($fee);

                $count++;
            }
        });

        return $count ?? 0;
    }

    /**
     * Check if a membership expiring notification has been sent.
     */
    private function hasBeenNotified(Membership $membership): bool
    {
        if ($membership->notes === null) {
            return false;
        }

        // Check for notification marker in notes
        $currentYear = (new DateTimeImmutable())->format('Y');

        return str_contains($membership->notes, "[EXPIRING_NOTIFIED:{$currentYear}]");
    }

    /**
     * Mark membership as notified about expiration.
     */
    private function markAsNotified(Membership $membership): void
    {
        $currentYear = (new DateTimeImmutable())->format('Y');
        $marker = "[EXPIRING_NOTIFIED:{$currentYear}]";

        $notes = $membership->notes ?? '';
        if (!str_contains($notes, $marker)) {
            $membership->notes = trim($notes . ' ' . $marker);
            $this->membershipRepository->save($membership);
        }
    }

    /**
     * Check if an overdue event has been dispatched for this fee.
     */
    private function hasOverdueEventDispatched(\Modules\Memberships\Domain\Entities\MembershipFee $fee): bool
    {
        if ($fee->notes === null) {
            return false;
        }

        $currentMonth = (new DateTimeImmutable())->format('Y-m');

        return str_contains($fee->notes, "[OVERDUE_NOTIFIED:{$currentMonth}]");
    }

    /**
     * Mark fee as having had an overdue event dispatched.
     */
    private function markFeeOverdueNotified(\Modules\Memberships\Domain\Entities\MembershipFee $fee): void
    {
        $currentMonth = (new DateTimeImmutable())->format('Y-m');
        $marker = "[OVERDUE_NOTIFIED:{$currentMonth}]";

        $notes = $fee->notes ?? '';
        if (!str_contains($notes, $marker)) {
            $fee->notes = trim($notes . ' ' . $marker);
            $this->membershipFeeRepository->save($fee);
        }
    }

    /**
     * Calculate days until expiration.
     */
    private function calculateDaysUntilExpiration(DateTimeImmutable $endDate, DateTimeImmutable $today): int
    {
        $diff = $today->diff($endDate);

        return $diff->invert ? 0 : $diff->days;
    }
}
