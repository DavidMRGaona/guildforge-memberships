<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Knowledge\Services\KnowledgeQueryServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Pages\KnowledgeBasePage;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\CreateFeeStructure;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\EditFeeStructure;
use Modules\Memberships\Filament\Resources\FeeStructureResource\Pages\ListFeeStructures;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\CreateMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\EditMember;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Who sees each of the module's guides, and the help button on its panel pages.
 */
final class MembershipsGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    /** For accounts linked to a member record. */
    private const array MEMBER_GUIDES = [
        'memberships.membership',
        'memberships.membership-emails',
    ];

    private const array PANEL_GUIDES = [
        'memberships.panel.expiry-and-notices',
        'memberships.panel.fee-structures',
        'memberships.panel.manage-members',
        'memberships.panel.member-role',
        'memberships.panel.memberships',
        'memberships.panel.overview',
        'memberships.panel.settings',
    ];

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private int $memberCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel(
            [MemberResource::class, FeeStructureResource::class],
            [MembershipSettings::class],
        );
        $this->artisanCommand('knowledge:sync')->assertSuccessful()->run();
    }

    public function test_an_admin_sees_every_panel_guide_rendered_without_raw_markup(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame(self::PANEL_GUIDES, $this->visibleGuides($admin, GuideAudience::Panel));
        foreach (self::PANEL_GUIDES as $id) {
            $this->assertRendersCleanly($admin, GuideAudience::Panel, $id);
        }
    }

    public function test_each_permission_shows_only_its_panel_guides(): void
    {
        $this->assertSame(
            ['memberships.panel.expiry-and-notices', 'memberships.panel.overview'],
            $this->visibleGuides($this->userWith('memberships:members.view_any'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['memberships.panel.manage-members'],
            $this->visibleGuides($this->userWith('memberships:members.create'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['memberships.panel.manage-members', 'memberships.panel.memberships'],
            $this->visibleGuides($this->userWith('memberships:members.update'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['memberships.panel.fee-structures', 'memberships.panel.overview'],
            $this->visibleGuides($this->userWith('memberships:fee_structures.view_any'), GuideAudience::Panel),
        );
        $this->assertSame(
            ['memberships.panel.expiry-and-notices', 'memberships.panel.member-role', 'memberships.panel.overview', 'memberships.panel.settings'],
            $this->visibleGuides($this->userWith('memberships:settings.manage'), GuideAudience::Panel),
        );
        $this->assertSame([], $this->visibleGuides(UserModel::factory()->editor()->create(), GuideAudience::Panel));
    }

    public function test_site_guides_are_only_for_accounts_linked_to_a_member_record(): void
    {
        $member = UserModel::factory()->create();
        $this->memberLinkedTo($member);

        $this->assertSame(self::MEMBER_GUIDES, $this->visibleGuides($member, GuideAudience::Site));
        $this->assertSame([], $this->visibleGuides(UserModel::factory()->create(), GuideAudience::Site));
        foreach (self::MEMBER_GUIDES as $id) {
            $this->assertRendersCleanly($member, GuideAudience::Site, $id);
        }
    }

    public function test_the_panel_pages_open_their_guide(): void
    {
        $this->actingAsPanelAdmin();
        $member = ['record' => $this->memberLinkedTo(UserModel::factory()->create())->getRouteKey()];
        $feeStructure = ['record' => FeeStructureModel::create([
            'id' => (string) Str::uuid(),
            'member_type' => 'regular',
            'period_type' => 'calendar_year',
            'amount' => '30.00',
            'currency' => 'EUR',
            'valid_from' => now()->startOfYear(),
            'is_default' => true,
        ])->getRouteKey()];

        $pages = [
            [ListMembers::class, [], 'memberships.panel.overview'],
            [CreateMember::class, [], 'memberships.panel.manage-members'],
            [EditMember::class, $member, 'memberships.panel.memberships'],
            [ListFeeStructures::class, [], 'memberships.panel.fee-structures'],
            [CreateFeeStructure::class, [], 'memberships.panel.fee-structures'],
            [EditFeeStructure::class, $feeStructure, 'memberships.panel.fee-structures'],
            [MembershipSettings::class, [], 'memberships.panel.settings'],
        ];

        foreach ($pages as [$page, $parameters, $guide]) {
            Livewire::test($page, $parameters)
                ->assertActionVisible('contextualHelp')
                ->assertActionHasUrl('contextualHelp', KnowledgeBasePage::getUrl(['guia' => $guide]));
        }
    }

    private function assertRendersCleanly(UserModel $user, GuideAudience $audience, string $id): void
    {
        $guide = $this->queries()->guide($user, $audience, GuideId::fromString($id));
        $this->assertNotNull($guide, $id);
        $this->assertStringNotContainsString('[[', $guide->html, $id);
        $this->assertStringNotContainsString('{{', $guide->html, $id);
        $this->assertStringNotContainsString('[!', $guide->html, $id);
    }

    /**
     * @return list<string>
     */
    private function visibleGuides(UserModel $user, GuideAudience $audience): array
    {
        $ids = array_values(array_filter(
            $this->queries()->visibleIds($user, $audience),
            static fn (string $id): bool => str_starts_with($id, 'memberships.'),
        ));
        sort($ids);

        return $ids;
    }

    private function memberLinkedTo(UserModel $user): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2026-%04d', ++$this->memberCount),
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'member_type' => 'regular',
            'status' => 'active',
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);
    }

    private function userWith(string $permissionKey): UserModel
    {
        $role = RoleModel::query()->create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => $permissionKey],
            ['label' => $permissionKey, 'resource' => 'memberships', 'action' => 'test', 'module' => 'memberships'],
        );
        $role->permissions()->attach($permission->id);
        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function queries(): KnowledgeQueryServiceInterface
    {
        return $this->app->make(KnowledgeQueryServiceInterface::class);
    }
}
