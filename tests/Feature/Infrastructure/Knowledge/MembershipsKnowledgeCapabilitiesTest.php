<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use stdClass;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The capability the site guides use answers as the module's records do: the person's account
 * is linked to a member record that has not been deleted.
 */
final class MembershipsKnowledgeCapabilitiesTest extends ModuleTestCase
{
    private const string HAS_MEMBER_RECORD = 'memberships.has_member_record';

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private KnowledgeCapabilityRegistryInterface $capabilities;

    private int $memberCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capabilities = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
    }

    public function test_the_module_registers_its_capability(): void
    {
        $this->assertContains(self::HAS_MEMBER_RECORD, $this->capabilities->keys());
    }

    public function test_an_account_linked_to_a_member_record_passes(): void
    {
        $user = UserModel::factory()->create();
        $this->memberLinkedTo($user);

        $this->assertTrue($this->capabilities->evaluate(self::HAS_MEMBER_RECORD, $user));
    }

    public function test_any_status_of_the_record_counts(): void
    {
        // A suspended member still receives the module's emails about their membership
        $user = UserModel::factory()->create();
        $this->memberLinkedTo($user, 'suspended');

        $this->assertTrue($this->capabilities->evaluate(self::HAS_MEMBER_RECORD, $user));
    }

    public function test_an_account_without_a_member_record_does_not_pass(): void
    {
        $this->memberLinkedTo(UserModel::factory()->create());

        $this->assertFalse($this->capabilities->evaluate(self::HAS_MEMBER_RECORD, UserModel::factory()->create()));
    }

    public function test_a_deleted_member_record_does_not_count(): void
    {
        $user = UserModel::factory()->create();
        $this->memberLinkedTo($user)->delete();

        $this->assertFalse($this->capabilities->evaluate(self::HAS_MEMBER_RECORD, $user));
    }

    public function test_something_that_is_not_an_account_does_not_pass(): void
    {
        $this->assertFalse($this->capabilities->evaluate(self::HAS_MEMBER_RECORD, new stdClass));
    }

    private function memberLinkedTo(UserModel $user, string $status = 'active'): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', ++$this->memberCount),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'member_type' => 'regular',
            'status' => $status,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
    }
}
