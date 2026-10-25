<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final class MembershipStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MemberModel::class) ?? false;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $memberRepository = resolve(MemberRepositoryInterface::class);
        $feeRepository = resolve(MembershipFeeRepositoryInterface::class);

        $totalMembers = $memberRepository->count();

        $activeMembers = $memberRepository->countByStatus(MemberStatus::Active);

        $membersWithActiveMembership = $memberRepository->countWithActiveMembership();

        // Permanent members (honorary by default) owe nothing
        $pendingPayments = $feeRepository->countUnpaid(resolve(MembershipSettingsReader::class)->permanentMemberTypes());

        return [
            Stat::make(
                __('memberships::memberships.widgets.stats.total_members'),
                $totalMembers
            )
                ->description(__('memberships::memberships.widgets.stats.total_members_description'))
                ->icon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make(
                __('memberships::memberships.widgets.stats.active_members'),
                $activeMembers
            )
                ->description(__('memberships::memberships.widgets.stats.active_members_description'))
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make(
                __('memberships::memberships.widgets.stats.members_with_membership'),
                $membersWithActiveMembership
            )
                ->description(__('memberships::memberships.widgets.stats.members_with_membership_description'))
                ->icon('heroicon-o-credit-card')
                ->color('info'),

            Stat::make(
                __('memberships::memberships.widgets.stats.pending_payments'),
                $pendingPayments
            )
                ->description(__('memberships::memberships.widgets.stats.pending_payments_description'))
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning'),
        ];
    }
}
