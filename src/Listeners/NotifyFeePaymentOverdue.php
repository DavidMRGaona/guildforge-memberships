<?php

declare(strict_types=1);

namespace Modules\Memberships\Listeners;

use DateTimeImmutable;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Events\FeePaymentOverdue;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Listeners\Concerns\NotifiesMember;
use Modules\Memberships\Notifications\PaymentOverdueNotification;

/**
 * Reminds the member of an unpaid fee (memberships:process-expired raises it at most once a
 * month per fee). Off until send_overdue_email is enabled.
 */
final readonly class NotifyFeePaymentOverdue
{
    use NotifiesMember;

    public function __construct(
        private MembershipRepositoryInterface $membershipRepository,
        private MemberRepositoryInterface $memberRepository,
        private MembershipSettingsReader $settings,
    ) {
    }

    public function handle(FeePaymentOverdue $event): void
    {
        if (! $this->settings->overdueEmailEnabled()) {
            return;
        }

        $membership = $this->membershipRepository->find(new MembershipId($event->membershipId));

        if ($membership === null || $membership->status === MembershipStatus::Cancelled) {
            return;
        }

        $member = $this->memberRepository->find($membership->memberId);

        // Only current members are chased: not those who left, were suspended or were expelled
        if ($member === null || ! $member->isActive() || in_array($member->memberType, $this->settings->permanentMemberTypes(), true)) {
            return;
        }

        $this->notifyMember($member, new PaymentOverdueNotification(
            memberName: $member->fullName(),
            formattedAmount: self::formatAmount($event->amountCents, $event->currency),
            dueDate: $event->dueDate,
            daysOverdue: self::daysSince($event->dueDate),
        ));
    }

    private static function formatAmount(int $cents, string $currency): string
    {
        $amount = number_format($cents / 100, 2, ',', '.');

        return $currency === 'EUR' ? "{$amount} €" : "{$amount} {$currency}";
    }

    private static function daysSince(DateTimeImmutable $dueDate): int
    {
        $today = new DateTimeImmutable('today');
        $diff = $dueDate->setTime(0, 0)->diff($today);

        return $diff->invert === 1 ? 0 : (int) $diff->days;
    }
}
