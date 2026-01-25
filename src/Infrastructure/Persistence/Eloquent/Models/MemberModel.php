<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;

/**
 * @property string $id
 * @property string|null $member_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property Carbon|null $birth_date
 * @property string|null $address
 * @property MemberType $member_type
 * @property MemberStatus $status
 * @property string|null $user_id
 * @property string|null $notes
 * @property Carbon $joined_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read UserModel|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MembershipModel> $memberships
 */
final class MemberModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'memberships_members';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (MemberModel $member): void {
            if ($member->member_number === null) {
                /** @var MemberNumberGeneratorInterface $generator */
                $generator = app(MemberNumberGeneratorInterface::class);
                $member->member_number = $generator->generate()->value;
            }
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'member_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'address',
        'member_type',
        'status',
        'user_id',
        'notes',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'member_type' => MemberType::class,
            'status' => MemberStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MembershipModel, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(MembershipModel::class, 'member_id');
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->first_name} {$this->last_name}",
        );
    }
}
