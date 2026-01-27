<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use App\Filament\Resources\BaseResource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;

class FeeStructureResource extends BaseResource
{
    protected static ?string $model = FeeStructureModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('memberships::memberships.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('memberships::memberships.navigation.fee_structures');
    }

    public static function getModelLabel(): string
    {
        return __('memberships::memberships.model.fee_structure.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('memberships::memberships.model.fee_structure.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('memberships::memberships.sections.fee_structure_data'))
                    ->schema([
                        Select::make('member_type')
                            ->label(__('memberships::memberships.fields.member_type'))
                            ->options([
                                MemberType::Regular->value => __('memberships::memberships.enums.member_type.regular'),
                                MemberType::Student->value => __('memberships::memberships.enums.member_type.student'),
                                MemberType::Senior->value => __('memberships::memberships.enums.member_type.senior'),
                                MemberType::Honorary->value => __('memberships::memberships.enums.member_type.honorary'),
                                MemberType::Founder->value => __('memberships::memberships.enums.member_type.founder'),
                            ])
                            ->required()
                            ->native(false),

                        Select::make('period_type')
                            ->label(__('memberships::memberships.fields.period_type'))
                            ->options([
                                MembershipPeriodType::CalendarYear->value => __('memberships::memberships.enums.membership_period_type.calendar_year'),
                                MembershipPeriodType::AcademicYear->value => __('memberships::memberships.enums.membership_period_type.academic_year'),
                                MembershipPeriodType::Rolling->value => __('memberships::memberships.enums.membership_period_type.rolling'),
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('amount')
                            ->label(__('memberships::memberships.fields.amount'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('€')
                            ->rules(['regex:/^\d+(\.\d{1,2})?$/']),

                        TextInput::make('currency')
                            ->label(__('memberships::memberships.fields.currency'))
                            ->default('EUR')
                            ->required()
                            ->maxLength(3)
                            ->helperText(__('memberships::memberships.fields.currency_help')),
                    ])
                    ->columns(2),

                Section::make(__('memberships::memberships.sections.validity'))
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label(__('memberships::memberships.fields.valid_from'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->default(now()),

                        DatePicker::make('valid_until')
                            ->label(__('memberships::memberships.fields.valid_until'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->after('valid_from')
                            ->nullable()
                            ->helperText(__('memberships::memberships.fields.valid_until_help')),

                        Checkbox::make('is_default')
                            ->label(__('memberships::memberships.fields.is_default'))
                            ->helperText(__('memberships::memberships.fields.is_default_help'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('memberships::memberships.sections.additional_info'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('memberships::memberships.fields.description'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_type')
                    ->label(__('memberships::memberships.fields.member_type'))
                    ->badge()
                    ->formatStateUsing(fn (MemberType $state): string => $state->label())
                    ->color(fn (MemberType $state): string => $state->color())
                    ->sortable(),

                TextColumn::make('period_type')
                    ->label(__('memberships::memberships.fields.period_type'))
                    ->badge()
                    ->formatStateUsing(fn (MembershipPeriodType $state): string => $state->label())
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('memberships::memberships.fields.amount'))
                    ->money(fn ($record): string => $record->currency ?? 'EUR')
                    ->sortable(),

                TextColumn::make('valid_from')
                    ->label(__('memberships::memberships.fields.valid_from'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label(__('memberships::memberships.fields.valid_until'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder(__('memberships::memberships.status.indefinite')),

                IconColumn::make('is_default')
                    ->label(__('memberships::memberships.fields.is_default'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('memberships::memberships.fields.created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('member_type')
                    ->label(__('memberships::memberships.filters.member_type'))
                    ->options([
                        MemberType::Regular->value => __('memberships::memberships.enums.member_type.regular'),
                        MemberType::Student->value => __('memberships::memberships.enums.member_type.student'),
                        MemberType::Senior->value => __('memberships::memberships.enums.member_type.senior'),
                        MemberType::Honorary->value => __('memberships::memberships.enums.member_type.honorary'),
                        MemberType::Founder->value => __('memberships::memberships.enums.member_type.founder'),
                    ]),

                SelectFilter::make('period_type')
                    ->label(__('memberships::memberships.filters.period_type'))
                    ->options([
                        MembershipPeriodType::CalendarYear->value => __('memberships::memberships.enums.membership_period_type.calendar_year'),
                        MembershipPeriodType::AcademicYear->value => __('memberships::memberships.enums.membership_period_type.academic_year'),
                        MembershipPeriodType::Rolling->value => __('memberships::memberships.enums.membership_period_type.rolling'),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('valid_from', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeeStructures::route('/'),
            'create' => Pages\CreateFeeStructure::route('/create'),
            'edit' => Pages\EditFeeStructure::route('/{record}/edit'),
        ];
    }
}
