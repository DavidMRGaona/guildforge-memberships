<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;

final class ExpiringMembershipsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 13;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('memberships::memberships.widgets.expiring_memberships.title'))
            ->query(
                MembershipModel::query()
                    ->where('status', MembershipStatus::Active->value)
                    ->where('end_date', '<=', now()->addDays(30))
                    ->where('end_date', '>=', now())
                    ->with('member')
                    ->orderBy('end_date', 'asc')
            )
            ->columns([
                TextColumn::make('member.full_name')
                    ->label(__('memberships::memberships.widgets.expiring_memberships.member'))
                    ->searchable(['first_name', 'last_name'])
                    ->url(fn (MembershipModel $record): string => MemberResource::getUrl('edit', ['record' => $record->member_id]))
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('memberships::memberships.widgets.expiring_memberships.end_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('days_remaining')
                    ->label(__('memberships::memberships.widgets.expiring_memberships.days_remaining'))
                    ->getStateUsing(function (MembershipModel $record): int {
                        return (int) now()->diffInDays($record->end_date, false);
                    })
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 7 => 'danger',
                        $state <= 14 => 'warning',
                        default => 'info',
                    })
                    ->suffix(fn (int $state): string => $state === 1 ? ' día' : ' días'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5);
    }
}
