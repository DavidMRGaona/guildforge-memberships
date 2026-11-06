<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature;

use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\MemberResource;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The module's help guides pass the knowledge base lint, so CI blocks a broken one.
 */
final class KnowledgeGuidesTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_guides_are_valid(): void
    {
        // The panel guides link to the module's panel pages, routed only once they are on the panel
        $this->registerOnAdminPanel(
            [MemberResource::class, FeeStructureResource::class],
            [MembershipSettings::class],
        );

        $this->assertModuleKnowledgeGuidesAreValid('memberships');
    }
}
