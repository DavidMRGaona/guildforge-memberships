<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\RelationManagers;

use App\Application\Services\TimezoneServiceInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Memberships\Application\DTOs\RecordPaymentDTO;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;

/**
 * The «Pagos» tab of a member: what each of their memberships costs and whether it is paid.
 * A fee is created pending (it becomes overdue the day after its due date, which feeds the
 * overdue widget and reminder) or already paid; a pending one gets its payment recorded later.
 */
class FeesRelationManager extends RelationManager
{
    protected static string $relationship = 'fees';

    private const string PAID = 'paid';

    private const string PENDING = 'pending';

    private const string OVERDUE = 'overdue';

    /**
     * The memberships and payments of a deleted member can be read, not changed.
     */
    public function isReadOnly(): bool
    {
        $member = $this->getOwnerRecord();

        return ($member instanceof MemberModel && $member->trashed()) || parent::isReadOnly();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('memberships::memberships.relation_managers.fees.title');
    }

    protected static function getModelLabel(): string
    {
        return __('memberships::memberships.model.fee.singular');
    }

    protected static function getPluralModelLabel(): string
    {
        return __('memberships::memberships.model.fee.plural');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('membership_id')
                    ->label(__('memberships::memberships.fields.membership'))
                    ->helperText(__('memberships::memberships.fields.membership_help'))
                    ->options(fn (): array => $this->membershipOptions())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $this->proposeAmount($set, $state))
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->label(__('memberships::memberships.fields.amount'))
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01)
                    ->rules(['regex:/^\d+(\.\d{1,2})?$/']),

                TextInput::make('currency')
                    ->label(__('memberships::memberships.fields.currency'))
                    ->default('EUR')
                    ->required()
                    ->maxLength(3),

                DatePicker::make('due_date')
                    ->label(__('memberships::memberships.fields.due_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => CalendarDate::today()->format('Y-m-d'))
                    ->required(),

                Toggle::make('is_paid')
                    ->label(__('memberships::memberships.fields.is_paid'))
                    ->default(false)
                    ->live()
                    ->columnSpanFull(),

                ...array_map(
                    fn (Component $field): Component => $field->visible(fn (Get $get): bool => (bool) $get('is_paid')),
                    $this->paymentFields(),
                ),

                Textarea::make('notes')
                    ->label(__('memberships::memberships.fields.notes'))
                    ->rows(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('membership_id')
                    ->label(__('memberships::memberships.fields.membership'))
                    ->formatStateUsing(fn (MembershipFeeModel $record): string => $this->shortMembershipLabel($record->membership))
                    ->visibleFrom('sm'),

                TextColumn::make('amount')
                    ->label(__('memberships::memberships.fields.amount'))
                    ->money(fn (MembershipFeeModel $record): string => $record->currency)
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('memberships::memberships.relation_managers.fees.columns.due_date'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->visibleFrom('sm'),

                TextColumn::make('payment_status')
                    ->label(__('memberships::memberships.relation_managers.fees.columns.payment_status'))
                    ->badge()
                    ->state(fn (MembershipFeeModel $record): string => $this->paymentStatus($record))
                    ->formatStateUsing(fn (string $state): string => __('memberships::memberships.enums.payment_status.'.$state))
                    ->color(fn (string $state): string => match ($state) {
                        self::PAID => 'success',
                        self::OVERDUE => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('paid_at')
                    ->label(__('memberships::memberships.relation_managers.fees.columns.paid_at'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    // The status already says whether it is paid: on narrow screens the row
                    // actions need the room
                    ->visibleFrom('lg'),

                TextColumn::make('payment_method')
                    ->label(__('memberships::memberships.fields.payment_method'))
                    ->formatStateUsing(fn (PaymentMethod $state): string => $state->label())
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('transaction_reference')
                    ->label(__('memberships::memberships.fields.transaction_reference'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('due_date', 'desc')
            ->emptyStateDescription(__('memberships::memberships.relation_managers.fees.empty_state_description'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('memberships::memberships.relation_managers.fees.create'))
                    ->modalHeading(__('memberships::memberships.relation_managers.fees.create'))
                    ->using(fn (array $data, MembershipFeeServiceInterface $fees): MembershipFeeModel => $this->createFee($data, $fees)),
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->label(__('memberships::memberships.relation_managers.fees.record_payment'))
                    ->icon('heroicon-o-banknotes')
                    ->button()
                    ->labeledFrom('md')
                    ->color('success')
                    ->visible(fn (MembershipFeeModel $record): bool => $record->paid_at === null && ! $this->isReadOnly())
                    ->authorize('update')
                    ->modalHeading(__('memberships::memberships.relation_managers.fees.record_payment'))
                    ->schema($this->paymentFields())
                    ->action(function (MembershipFeeModel $record, array $data, MembershipFeeServiceInterface $fees): void {
                        $fees->recordPayment($this->paymentFrom($record->id, $data));

                        Notification::make()
                            ->title(__('memberships::memberships.relation_managers.fees.payment_recorded'))
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    EditAction::make()
                        ->modalHeading(__('memberships::memberships.relation_managers.fees.edit'))
                        ->mutateRecordDataUsing(fn (array $data, MembershipFeeModel $record): array => [
                            ...$data,
                            'is_paid' => $record->paid_at !== null,
                            'paid_on' => $record->paid_at === null ? null : app(TimezoneServiceInterface::class)->toDisplay($record->paid_at)->format('Y-m-d'),
                        ])
                        ->using(fn (MembershipFeeModel $record, array $data): MembershipFeeModel => $this->updateFee($record, $data)),

                    // Irreversible: confirmed twice, the second time with a box that says so
                    DeleteAction::make()
                        ->schema([
                            Checkbox::make('confirmation')
                                ->label(__('memberships::memberships.relation_managers.fees.delete_confirmation_check'))
                                ->accepted()
                                ->required(),
                        ]),
                ]),
            ]);
    }

    /**
     * Payment date, method and reference: asked when a fee is created already paid, edited or
     * when its payment is recorded.
     *
     * @return list<Component>
     */
    private function paymentFields(): array
    {
        return [
            DatePicker::make('paid_on')
                ->label(__('memberships::memberships.fields.paid_on'))
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(fn (): string => CalendarDate::today()->format('Y-m-d'))
                ->required(),

            Select::make('payment_method')
                ->label(__('memberships::memberships.fields.payment_method'))
                ->options(array_combine(
                    PaymentMethod::values(),
                    array_map(fn (PaymentMethod $method): string => $method->label(), PaymentMethod::cases()),
                ))
                ->required()
                ->native(false),

            TextInput::make('transaction_reference')
                ->label(__('memberships::memberships.fields.transaction_reference'))
                ->maxLength(255),
        ];
    }

    /**
     * The member's memberships, newest first, as «Año natural · 01/01/2026 – 31/12/2026».
     *
     * @return array<string, string>
     */
    private function membershipOptions(): array
    {
        $member = $this->getOwnerRecord();

        if (! $member instanceof MemberModel) {
            return [];
        }

        return $member->memberships()
            ->orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (MembershipModel $membership): array => [$membership->id => $this->membershipLabel($membership)])
            ->all();
    }

    /**
     * Fills in the amount of the default fee structure of the member's type and the
     * membership's period type, when there is one; the person can still change it.
     */
    private function proposeAmount(Set $set, ?string $membershipId): void
    {
        $member = $this->getOwnerRecord();
        $membership = $membershipId === null || ! $member instanceof MemberModel
            ? null
            : $member->memberships()->whereKey($membershipId)->first();

        if (! $membership instanceof MembershipModel) {
            return;
        }

        $default = FeeStructureModel::defaultsFor($member->member_type, $membership->period_type)->first();

        if ($default instanceof FeeStructureModel) {
            $set('amount', $default->amount);
            $set('currency', $default->currency);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createFee(array $data, MembershipFeeServiceInterface $fees): MembershipFeeModel
    {
        $currency = strtoupper((string) $data['currency']);
        $fee = $fees->createFee(
            membershipId: (string) $data['membership_id'],
            amountCents: Money::fromAmount((string) $data['amount'], $currency)->toCents(),
            currency: $currency,
            dueDate: (string) $data['due_date'],
            notes: $this->optionalText($data['notes'] ?? null),
        );

        if ((bool) ($data['is_paid'] ?? false)) {
            $fees->recordPayment($this->paymentFrom($fee->id, $data));
        }

        return MembershipFeeModel::query()->findOrFail($fee->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateFee(MembershipFeeModel $record, array $data): MembershipFeeModel
    {
        $isPaid = (bool) ($data['is_paid'] ?? false);

        $record->fill([
            'membership_id' => $data['membership_id'],
            'amount' => $data['amount'],
            'currency' => strtoupper((string) $data['currency']),
            'due_date' => $data['due_date'],
            'notes' => $this->optionalText($data['notes'] ?? null),
            'paid_at' => $isPaid ? CalendarDate::parse((string) $data['paid_on']) : null,
            'payment_method' => $isPaid ? $data['payment_method'] : null,
            'transaction_reference' => $isPaid ? $this->optionalText($data['transaction_reference'] ?? null) : null,
        ])->save();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paymentFrom(string $feeId, array $data): RecordPaymentDTO
    {
        return new RecordPaymentDTO(
            feeId: $feeId,
            paymentMethod: (string) $data['payment_method'],
            transactionReference: $this->optionalText($data['transaction_reference'] ?? null),
            // A calendar day of the display zone: its midnight there
            paidAt: (string) $data['paid_on'],
        );
    }

    private function paymentStatus(MembershipFeeModel $fee): string
    {
        if ($fee->paid_at !== null) {
            return self::PAID;
        }

        // Overdue from the day after the due date, read in the display zone
        return $fee->due_date->toDateString() < CalendarDate::today()->format('Y-m-d') ? self::OVERDUE : self::PENDING;
    }

    /**
     * «Año natural · 2026» or «Año académico · 2026–2027»: the table has no room for full dates.
     */
    private function shortMembershipLabel(MembershipModel $membership): string
    {
        $start = $membership->start_date->format('Y');
        $end = $membership->end_date->format('Y');

        return $membership->period_type->label().' · '.($start === $end ? $start : $start.'–'.$end);
    }

    private function membershipLabel(MembershipModel $membership): string
    {
        return $membership->period_type->label()
            .' · '.$membership->start_date->format('d/m/Y')
            .' – '.$membership->end_date->format('d/m/Y');
    }

    private function optionalText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
