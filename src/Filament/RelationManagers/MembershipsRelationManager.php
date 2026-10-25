<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\RelationManagers;

use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('memberships::memberships.sections.memberships');
    }

    protected static function getModelLabel(): string
    {
        return __('memberships::memberships.model.membership.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('memberships::memberships.model.membership.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period_type')
                    ->label(__('memberships::memberships.fields.period_type'))
                    ->options([
                        MembershipPeriodType::CalendarYear->value => __('memberships::memberships.enums.membership_period_type.calendar_year'),
                        MembershipPeriodType::AcademicYear->value => __('memberships::memberships.enums.membership_period_type.academic_year'),
                        MembershipPeriodType::Rolling->value => __('memberships::memberships.enums.membership_period_type.rolling'),
                    ])
                    ->default(fn (MembershipSettingsReader $settings): string => $settings->defaultPeriodType()->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->calculateEndDate($get, $set)),

                DatePicker::make('start_date')
                    ->label(__('memberships::memberships.fields.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => $this->calculateEndDate($get, $set)),

                DatePicker::make('end_date')
                    ->label(__('memberships::memberships.fields.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->required()
                    ->after('start_date'),

                Select::make('status')
                    ->label(__('memberships::memberships.fields.status'))
                    ->options([
                        MembershipStatus::Pending->value => __('memberships::memberships.enums.membership_status.pending'),
                        MembershipStatus::Active->value => __('memberships::memberships.enums.membership_status.active'),
                        MembershipStatus::Expired->value => __('memberships::memberships.enums.membership_status.expired'),
                        MembershipStatus::Cancelled->value => __('memberships::memberships.enums.membership_status.cancelled'),
                    ])
                    ->default(MembershipStatus::Pending->value)
                    ->required()
                    ->native(false),

                Textarea::make('notes')
                    ->label(__('memberships::memberships.fields.notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    private function calculateEndDate(Get $get, Set $set): void
    {
        $periodType = $get('period_type');
        $startDate = $get('start_date');

        if ($periodType === null || $startDate === null) {
            return;
        }

        $start = Carbon::parse($startDate);
        $academicStartMonth = app(MembershipSettingsReader::class)->academicStartMonth();

        $endDate = match ($periodType) {
            MembershipPeriodType::CalendarYear->value => $start->copy()->endOfYear(),
            MembershipPeriodType::AcademicYear->value => $this->calculateAcademicYearEnd($start, $academicStartMonth),
            MembershipPeriodType::Rolling->value => $start->copy()->addYear()->subDay(),
            default => null,
        };

        if ($endDate !== null) {
            $set('end_date', $endDate->format('Y-m-d'));
        }
    }

    private function calculateAcademicYearEnd(Carbon $start, int $academicStartMonth): Carbon
    {
        $year = $start->year;

        // If we're before the academic year starts, the year ends in the current year
        // If we're on or after the academic year starts, the year ends in the next year
        if ($start->month < $academicStartMonth) {
            $endYear = $year;
        } else {
            $endYear = $year + 1;
        }

        // Academic year ends the day before the start month of the next academic year
        return Carbon::createFromDate($endYear, $academicStartMonth, 1)->startOfDay()->subDay();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_type')
                    ->label(__('memberships::memberships.fields.period_type'))
                    ->badge()
                    ->formatStateUsing(fn (MembershipPeriodType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label(__('memberships::memberships.fields.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('memberships::memberships.fields.end_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('memberships::memberships.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (MembershipStatus $state): string => $state->label())
                    ->color(fn (MembershipStatus $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('activated_at')
                    ->label(__('memberships::memberships.fields.activated_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateDescription(__('memberships::memberships.relation_managers.memberships.empty_state_description'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('memberships::memberships.pages.create_membership'))
                    ->modalHeading(__('memberships::memberships.pages.create_membership')),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading(__('memberships::memberships.pages.edit_membership')),
                DeleteAction::make(),
            ]);
    }
}
