<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use Modules\Memberships\Application\DTOs\CreateMemberDTO;
use Modules\Memberships\Application\DTOs\MemberDTO;
use Modules\Memberships\Application\DTOs\UpdateMemberDTO;
use Modules\Memberships\Application\Services\MemberServiceInterface;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Tests\Support\Modules\ModuleTestCase;

final class MemberServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_a_partial_update_keeps_the_fields_it_does_not_name(): void
    {
        $member = $this->createMember();

        $updated = $this->service()->update(new UpdateMemberDTO(
            id: $member->id,
            lastName: 'Roe',
            phone: '600 000 001',
        ));

        $this->assertSame('John', $updated->firstName);
        $this->assertSame('Roe', $updated->lastName);
        $this->assertSame('1990-05-17', $updated->birthDate);
        $this->assertSame('john@example.com', $updated->email);
        $this->assertSame('600 000 001', $updated->phone);
        $this->assertSame('Calle Ejemplo 1', $updated->address);
    }

    public function test_an_update_saves_the_member_type_and_the_notes(): void
    {
        $member = $this->createMember();

        $this->service()->update(new UpdateMemberDTO(
            id: $member->id,
            phone: '622 222 222',
            memberType: MemberType::Student->value,
            notes: 'New note',
        ));

        $stored = $this->service()->findOrFail($member->id);
        $this->assertSame('622 222 222', $stored->phone);
        $this->assertSame(MemberType::Student->value, $stored->memberType);
        $this->assertSame('New note', $stored->notes);
    }

    public function test_an_update_that_does_not_name_the_member_type_or_the_notes_keeps_them(): void
    {
        $member = $this->service()->create(new CreateMemberDTO(
            firstName: 'John',
            lastName: 'Doe',
            memberType: MemberType::Senior->value,
            notes: 'Original note',
        ));

        $this->service()->update(new UpdateMemberDTO(id: $member->id, lastName: 'Roe'));

        $stored = $this->service()->findOrFail($member->id);
        $this->assertSame(MemberType::Senior->value, $stored->memberType);
        $this->assertSame('Original note', $stored->notes);
    }

    public function test_a_member_is_found_by_its_number(): void
    {
        $member = $this->createMember();

        $found = $this->service()->findByMemberNumber($member->memberNumber);

        $this->assertNotNull($found);
        $this->assertSame($member->id, $found->id);
    }

    public function test_a_new_member_does_not_reuse_the_number_of_a_deleted_one(): void
    {
        $first = $this->createMember();
        $last = $this->createMember();
        $this->service()->delete($last->id);

        $next = $this->createMember();

        $this->assertNotSame($last->memberNumber, $next->memberNumber);
        $this->assertNotSame($first->memberNumber, $next->memberNumber);
        $this->assertSame($this->sequence($last->memberNumber) + 1, $this->sequence($next->memberNumber));
    }

    public function test_a_member_created_in_the_panel_does_not_reuse_the_number_of_a_deleted_one(): void
    {
        $this->createMember();
        $last = $this->createMember();
        $this->service()->delete($last->id);

        $panelMember = MemberModel::create([
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'member_type' => MemberType::Regular->value,
            'status' => MemberStatus::Active->value,
            'joined_at' => now(),
        ]);

        $this->assertSame($this->sequence($last->memberNumber) + 1, $this->sequence($panelMember->member_number));
    }

    public function test_a_deleted_members_number_counts_as_taken(): void
    {
        $member = $this->createMember();
        $this->service()->delete($member->id);

        $this->assertTrue($this->app->make(MemberRepositoryInterface::class)->existsByMemberNumber(new MemberNumber($member->memberNumber)));
    }

    private function sequence(string $memberNumber): int
    {
        return (new MemberNumber($memberNumber))->sequence();
    }

    private function createMember(): MemberDTO
    {
        return $this->service()->create(new CreateMemberDTO(
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@example.com',
            phone: '600 000 000',
            birthDate: '1990-05-17',
            address: 'Calle Ejemplo 1',
        ));
    }

    private function service(): MemberServiceInterface
    {
        return $this->app->make(MemberServiceInterface::class);
    }
}
