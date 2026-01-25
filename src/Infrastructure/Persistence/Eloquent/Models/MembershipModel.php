<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;

/**
 * @property string $id
 * @property string $member_id
 * @property MembershipPeriodType $period_type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property MembershipStatus $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $cancelled_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read MemberModel $member
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MembershipFeeModel> $fees
 */
final class MembershipModel extends Model
{
    use HasUuids;

    protected $table = 'memberships_memberships';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (MembershipModel $membership): void {
            if ($membership->status === null) {
                $membership->status = MembershipStatus::Pending;
            }

            // Set activated_at if creating with Active status
            if ($membership->status === MembershipStatus::Active && $membership->activated_at === null) {
                $membership->activated_at = now();
            }
        });

        static::updating(function (MembershipModel $membership): void {
            // Set activated_at when status changes to Active
            if ($membership->isDirty('status') && $membership->status === MembershipStatus::Active && $membership->activated_at === null) {
                $membership->activated_at = now();
            }

            // Set cancelled_at when status changes to Cancelled
            if ($membership->isDirty('status') && $membership->status === MembershipStatus::Cancelled && $membership->cancelled_at === null) {
                $membership->cancelled_at = now();
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'member_id',
        'period_type',
        'start_date',
        'end_date',
        'status',
        'activated_at',
        'cancelled_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_type' => MembershipPeriodType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => MembershipStatus::class,
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MemberModel, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'member_id');
    }

    /**
     * @return HasMany<MembershipFeeModel, $this>
     */
    public function fees(): HasMany
    {
        return $this->hasMany(MembershipFeeModel::class, 'membership_id');
    }
}
