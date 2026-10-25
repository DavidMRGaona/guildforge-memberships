<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use Modules\Memberships\Application\DTOs\CreateMemberDTO;
use Modules\Memberships\Application\DTOs\MemberDTO;
use Modules\Memberships\Application\DTOs\UpdateMemberDTO;
use Modules\Memberships\Application\Services\MemberServiceInterface;
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

    public function test_a_member_is_found_by_its_number(): void
    {
        $member = $this->createMember();

        $found = $this->service()->findByMemberNumber($member->memberNumber);

        $this->assertNotNull($found);
        $this->assertSame($member->id, $found->id);
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
