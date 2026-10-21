<?php

declare(strict_types=1);

namespace Modules\Memberships\Database\Seeders;

use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;

final class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data: the core runs every module seeder on install and update, production included
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Enabling the module in tests runs this seeder too, and the suites expect no members
        if (! app()->environment('local')) {
            return;
        }

        $this->seedFeeStructures();
        $this->seedMembers($this->samplePeople());
    }

    /**
     * Yearly fees per member type. Honorary members (permanent by default) pay none, so
     * they have no structure.
     */
    private function seedFeeStructures(): void
    {
        $structures = [
            [MemberType::Regular, 60, 'Cuota anual de socio'],
            [MemberType::Student, 30, 'Cuota anual reducida para estudiantes'],
            [MemberType::Senior, 30, 'Cuota anual reducida para mayores'],
            [MemberType::Founder, 60, 'Cuota anual de socio fundador'],
        ];

        foreach ($structures as [$type, $amount, $description]) {
            FeeStructureModel::firstOrCreate(
                ['member_type' => $type->value, 'period_type' => MembershipPeriodType::CalendarYear->value],
                [
                    'amount' => $amount,
                    'currency' => 'EUR',
                    'valid_from' => now()->startOfYear()->toDateString(),
                    'description' => $description,
                    'is_default' => true,
                ],
            );
        }
    }

    /**
     * Writes straight to the tables, so no expiry, overdue or payment email is sent.
     * Scenarios, by person:
     * - John Doe (regular): active membership with the fee paid, and last year's, expired
     * - Jane Doe (student): active membership expiring in 15 days, fee paid
     * - Richard Roe (senior): active membership with the fee overdue
     * - Mary Major (honorary): permanent member, no fee
     *
     * @param  array<string, UserModel>  $people
     */
    private function seedMembers(array $people): void
    {
        $yearStart = now()->startOfYear();
        $yearEnd = now()->endOfYear();

        $john = $this->member($people['john'], MemberType::Regular, 'Cuenta de ejemplo con la cuota al día.');
        $this->membership($john, MembershipPeriodType::CalendarYear, $yearStart->copy()->subYear(), $yearEnd->copy()->subYear(), MembershipStatus::Expired);
        $this->fee($this->membership($john, MembershipPeriodType::CalendarYear, $yearStart, $yearEnd, MembershipStatus::Active), 60, $yearStart->copy()->addDays(30), paid: true);

        $jane = $this->member($people['jane'], MemberType::Student, 'Su membresía caduca pronto.');
        $janeEnd = now()->addDays(15)->startOfDay();
        $this->fee($this->membership($jane, MembershipPeriodType::Rolling, $janeEnd->copy()->subYear()->addDay(), $janeEnd, MembershipStatus::Active), 30, $janeEnd->copy()->subYear()->addDays(30), paid: true);

        $richard = $this->member($people['richard'], MemberType::Senior, 'Tiene una cuota pendiente de pago.');
        $this->fee($this->membership($richard, MembershipPeriodType::CalendarYear, $yearStart, $yearEnd, MembershipStatus::Active), 30, now()->subDays(25)->startOfDay(), paid: false);

        $mary = $this->member($people['mary'], MemberType::Honorary, 'Socia honoraria: permanente, sin cuota.');
        $this->membership($mary, MembershipPeriodType::CalendarYear, $yearStart, $yearEnd, MembershipStatus::Active);
    }

    private function member(UserModel $user, MemberType $type, string $notes): MemberModel
    {
        [$firstName, $lastName] = explode(' ', $user->name, 2);

        return MemberModel::firstOrCreate(
            ['email' => $user->email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'member_type' => $type->value,
                'status' => MemberStatus::Active->value,
                'user_id' => $user->id,
                'notes' => $notes,
                'joined_at' => now()->subYears(2)->startOfDay(),
            ],
        );
    }

    private function membership(MemberModel $member, MembershipPeriodType $period, Carbon $start, Carbon $end, MembershipStatus $status): MembershipModel
    {
        return MembershipModel::updateOrCreate(
            ['member_id' => $member->id, 'status' => $status->value],
            [
                'period_type' => $period->value,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'activated_at' => $start,
            ],
        );
    }

    private function fee(MembershipModel $membership, int $amount, Carbon $dueDate, bool $paid): void
    {
        MembershipFeeModel::updateOrCreate(
            ['membership_id' => $membership->id],
            [
                'amount' => $amount,
                'currency' => 'EUR',
                'due_date' => $dueDate->toDateString(),
                'paid_at' => $paid ? $dueDate->copy()->subDays(5) : null,
                'payment_method' => $paid ? PaymentMethod::BankTransfer->value : null,
                'transaction_reference' => $paid ? 'DEMO-0001' : null,
            ],
        );
    }

    /**
     * @return array<string, UserModel>
     */
    private function samplePeople(): array
    {
        $memberRole = app(MemberRoleServiceInterface::class);
        $people = [];

        foreach (['john' => 'John Doe', 'jane' => 'Jane Doe', 'richard' => 'Richard Roe', 'mary' => 'Mary Major'] as $key => $name) {
            $email = Str::lower(str_replace(' ', '.', $name)).'@example.local';
            $user = UserModel::where('email', $email)->first();

            if ($user === null) {
                $user = new UserModel;
                $user->forceFill([
                    'name' => $name,
                    'display_name' => $name,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                ])->save();
            }

            $memberRole->assignTo($user);
            $people[$key] = $user;
        }

        return $people;
    }
}
