<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Memberships\Domain\Enums\PaymentMethod;

class MembershipFeesRelationManager extends RelationManager
{
    protected static string $relationship = 'fees';

    public static function getTitle($ownerRecord, $pageClass): string
    {
        return __('memberships::memberships.sections.fees');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('payment_method')
                    ->label(__('memberships::memberships.fields.payment_method'))
                    ->options([
                        PaymentMethod::Cash->value => __('memberships::memberships.enums.payment_method.cash'),
                        PaymentMethod::BankTransfer->value => __('memberships::memberships.enums.payment_method.bank_transfer'),
                        PaymentMethod::Card->value => __('memberships::memberships.enums.payment_method.card'),
                        PaymentMethod::Other->value => __('memberships::memberships.enums.payment_method.other'),
                    ])
                    ->native(false),

                TextInput::make('transaction_reference')
                    ->label(__('memberships::memberships.fields.transaction_reference'))
                    ->maxLength(255),

                DateTimePicker::make('paid_at')
                    ->label(__('memberships::memberships.fields.paid_at'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label(__('memberships::memberships.fields.amount'))
                    ->money(fn ($record): string => $record->currency ?? 'EUR')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('memberships::memberships.fields.due_date'))
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label(__('memberships::memberships.fields.paid_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder(__('memberships::memberships.status.unpaid')),

                TextColumn::make('payment_method')
                    ->label(__('memberships::memberships.fields.payment_method'))
                    ->formatStateUsing(fn (?PaymentMethod $state): string => $state?->label() ?? '-')
                    ->icon(fn (?PaymentMethod $state): ?string => $state?->icon())
                    ->toggleable(),

                IconColumn::make('is_paid')
                    ->label(__('memberships::memberships.fields.is_paid'))
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->paid_at !== null)
                    ->sortable(),
            ])
            ->defaultSort('due_date', 'desc')
            ->actions([
                Action::make('record_payment')
                    ->label(__('memberships::memberships.actions.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn ($record): bool => $record->paid_at === null)
                    ->form([
                        Select::make('payment_method')
                            ->label(__('memberships::memberships.fields.payment_method'))
                            ->options([
                                PaymentMethod::Cash->value => __('memberships::memberships.enums.payment_method.cash'),
                                PaymentMethod::BankTransfer->value => __('memberships::memberships.enums.payment_method.bank_transfer'),
                                PaymentMethod::Card->value => __('memberships::memberships.enums.payment_method.card'),
                                PaymentMethod::Other->value => __('memberships::memberships.enums.payment_method.other'),
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('transaction_reference')
                            ->label(__('memberships::memberships.fields.transaction_reference'))
                            ->maxLength(255),

                        DateTimePicker::make('paid_at')
                            ->label(__('memberships::memberships.fields.paid_at'))
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'payment_method' => $data['payment_method'],
                            'transaction_reference' => $data['transaction_reference'] ?? null,
                            'paid_at' => $data['paid_at'],
                        ]);
                    }),
                EditAction::make(),
            ]);
    }
}
