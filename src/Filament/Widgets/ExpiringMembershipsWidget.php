<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Widgets;

use App\Application\Services\DashboardWidgetConfigServiceInterface;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final class ExpiringMembershipsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 13;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', MemberModel::class) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('memberships::memberships.widgets.expiring_memberships.title'))
            ->query(
                MembershipModel::query()
                    ->where('status', MembershipStatus::Active->value)
                    ->where('end_date', '<=', CalendarDate::today()->modify('+30 days')->format('Y-m-d'))
                    ->where('end_date', '>=', CalendarDate::today()->format('Y-m-d'))
                    ->excludingMemberTypes(app(MembershipSettingsReader::class)->permanentMemberTypes())
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
                    ->getStateUsing(fn (MembershipModel $record): int => (int) CalendarDate::today()->diff(CalendarDate::parse($record->end_date->toDateString()))->format('%r%a'))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 7 => 'danger',
                        $state <= 14 => 'warning',
                        default => 'info',
                    })
                    ->suffix(fn (int $state): string => $state === 1 ? ' día' : ' días'),
            ])
            ->paginated([$this->getConfiguredLimit()])
            ->defaultPaginationPageOption($this->getConfiguredLimit());
    }

    private function getConfiguredLimit(): int
    {
        return app(DashboardWidgetConfigServiceInterface::class)
            ->getLimit(self::class, 5);
    }
}
