<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;

/**
 * @property string $id
 * @property MemberType $member_type
 * @property MembershipPeriodType $period_type
 * @property string $amount
 * @property string $currency
 * @property array<string, mixed>|null $proration_rules
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $description
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class FeeStructureModel extends Model
{
    use HasUuids;

    protected $table = 'memberships_fee_structures';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'member_type',
        'period_type',
        'amount',
        'currency',
        'proration_rules',
        'valid_from',
        'valid_until',
        'description',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'member_type' => MemberType::class,
            'period_type' => MembershipPeriodType::class,
            'amount' => 'decimal:2',
            'proration_rules' => 'array',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_default' => 'boolean',
        ];
    }
}
