<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;

final class MembershipStatsWidget extends StatsOverviewWidget
{
    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalMembers = MemberModel::count();

        $activeMembers = MemberModel::where('status', MemberStatus::Active->value)->count();

        $membersWithActiveMembership = MemberModel::whereHas('memberships', function ($query): void {
            $query->where('status', MembershipStatus::Active->value);
        })->count();

        $pendingPayments = MembershipFeeModel::whereNull('paid_at')->count();

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
