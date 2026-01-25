<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Memberships\Domain\Enums\PaymentMethod;

/**
 * @property string $id
 * @property string $membership_id
 * @property string $amount
 * @property string $currency
 * @property Carbon $due_date
 * @property Carbon|null $paid_at
 * @property PaymentMethod|null $payment_method
 * @property string|null $transaction_reference
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MembershipModel $membership
 */
final class MembershipFeeModel extends Model
{
    use HasUuids;

    protected $table = 'memberships_fees';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'membership_id',
        'amount',
        'currency',
        'due_date',
        'paid_at',
        'payment_method',
        'transaction_reference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<MembershipModel, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(MembershipModel::class, 'membership_id');
    }
}
