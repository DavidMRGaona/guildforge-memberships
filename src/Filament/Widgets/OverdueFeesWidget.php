<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;

final class OverdueFeesWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('memberships::memberships.widgets.overdue_fees.title'))
            ->query(
                MembershipFeeModel::query()
                    ->whereNull('paid_at')
                    ->where('due_date', '<', now())
                    ->with(['membership.member'])
                    ->orderBy('due_date', 'asc')
            )
            ->columns([
                TextColumn::make('membership.member.full_name')
                    ->label(__('memberships::memberships.widgets.overdue_fees.member'))
                    ->searchable(['first_name', 'last_name'])
                    ->url(fn (MembershipFeeModel $record): string => MemberResource::getUrl('edit', ['record' => $record->membership->member_id]))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('memberships::memberships.widgets.overdue_fees.amount'))
                    ->money(fn (MembershipFeeModel $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('memberships::memberships.widgets.overdue_fees.due_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_overdue')
                    ->label(__('memberships::memberships.widgets.overdue_fees.days_overdue'))
                    ->getStateUsing(function (MembershipFeeModel $record): int {
                        return (int) $record->due_date->diffInDays(now(), false);
                    })
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state > 60 => 'danger',
                        $state > 30 => 'warning',
                        default => 'gray',
                    })
                    ->suffix(fn (int $state): string => $state === 1 ? ' día' : ' días'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
