<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Resources;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Filament\Resources\MemberResource\Pages;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

class MemberResource extends Resource
{
    protected static ?string $model = MemberModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('memberships::memberships.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('memberships::memberships.navigation.members');
    }

    public static function getModelLabel(): string
    {
        return __('memberships::memberships.model.member.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('memberships::memberships.model.member.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('memberships::memberships.sections.personal_data'))
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('memberships::memberships.fields.first_name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('last_name')
                            ->label(__('memberships::memberships.fields.last_name'))
                            ->required()
                            ->maxLength(255),

                        DatePicker::make('birth_date')
                            ->label(__('memberships::memberships.fields.birth_date'))
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->nullable(),

                        Select::make('member_type')
                            ->label(__('memberships::memberships.fields.member_type'))
                            ->options([
                                MemberType::Regular->value => __('memberships::memberships.enums.member_type.regular'),
                                MemberType::Student->value => __('memberships::memberships.enums.member_type.student'),
                                MemberType::Senior->value => __('memberships::memberships.enums.member_type.senior'),
                                MemberType::Honorary->value => __('memberships::memberships.enums.member_type.honorary'),
                                MemberType::Founder->value => __('memberships::memberships.enums.member_type.founder'),
                            ])
                            ->default(MemberType::Regular->value)
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                Section::make(__('memberships::memberships.sections.contact_data'))
                    ->schema([
                        TextInput::make('email')
                            ->label(__('memberships::memberships.fields.email'))
                            ->email()
                            ->maxLength(255)
                            ->nullable(),

                        TextInput::make('phone')
                            ->label(__('memberships::memberships.fields.phone'))
                            ->tel()
                            ->maxLength(20)
                            ->nullable(),

                        Textarea::make('address')
                            ->label(__('memberships::memberships.fields.address'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('memberships::memberships.sections.status'))
                    ->schema([
                        Select::make('status')
                            ->label(__('memberships::memberships.fields.status'))
                            ->options([
                                MemberStatus::Active->value => __('memberships::memberships.enums.member_status.active'),
                                MemberStatus::Inactive->value => __('memberships::memberships.enums.member_status.inactive'),
                                MemberStatus::Suspended->value => __('memberships::memberships.enums.member_status.suspended'),
                                MemberStatus::Expelled->value => __('memberships::memberships.enums.member_status.expelled'),
                            ])
                            ->default(MemberStatus::Active->value)
                            ->required()
                            ->native(false),

                        Select::make('user_id')
                            ->label(__('memberships::memberships.fields.user_id'))
                            ->relationship('user', 'name')
                            ->searchable()
                            ->nullable()
                            ->helperText(__('memberships::memberships.fields.user_id_help')),

                        DateTimePicker::make('joined_at')
                            ->label(__('memberships::memberships.fields.joined_at'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->default(now())
                            ->required(),

                        Textarea::make('notes')
                            ->label(__('memberships::memberships.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('member_number')
                    ->label(__('memberships::memberships.fields.member_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('full_name')
                    ->label(__('memberships::memberships.fields.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(query: function ($query, $direction) {
                        return $query->orderBy('first_name', $direction)
                            ->orderBy('last_name', $direction);
                    }),

                TextColumn::make('email')
                    ->label(__('memberships::memberships.fields.email'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('member_type')
                    ->label(__('memberships::memberships.fields.member_type'))
                    ->badge()
                    ->formatStateUsing(fn (MemberType $state): string => $state->label())
                    ->color(fn (MemberType $state): string => $state->color()),

                TextColumn::make('status')
                    ->label(__('memberships::memberships.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (MemberStatus $state): string => $state->label())
                    ->color(fn (MemberStatus $state): string => $state->color()),

                TextColumn::make('joined_at')
                    ->label(__('memberships::memberships.fields.joined_at'))
                    ->date('d/m/Y')
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

                SelectFilter::make('status')
                    ->label(__('memberships::memberships.filters.status'))
                    ->options([
                        MemberStatus::Active->value => __('memberships::memberships.enums.member_status.active'),
                        MemberStatus::Inactive->value => __('memberships::memberships.enums.member_status.inactive'),
                        MemberStatus::Suspended->value => __('memberships::memberships.enums.member_status.suspended'),
                        MemberStatus::Expelled->value => __('memberships::memberships.enums.member_status.expelled'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('joined_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
