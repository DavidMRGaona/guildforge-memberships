<?php

declare(strict_types=1);

namespace Modules\Memberships;

use App\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Modules\Memberships\Application\Services\FeeStructureServiceInterface;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Application\Services\MemberServiceInterface;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Application\Services\MembershipServiceInterface;
use Modules\Memberships\Application\Services\ProrationCalculatorInterface;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Domain\Events\MembershipActivated;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\FeeStructureRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentFeeStructureRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMemberRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMembershipFeeRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMembershipRepository;
use Modules\Memberships\Infrastructure\Services\FeeStructureService;
use Modules\Memberships\Infrastructure\Services\MemberNumberGenerator;
use Modules\Memberships\Infrastructure\Services\MemberService;
use Modules\Memberships\Infrastructure\Services\MembershipFeeService;
use Modules\Memberships\Infrastructure\Services\MembershipService;
use Modules\Memberships\Infrastructure\Services\ProrationCalculator;
use Modules\Memberships\Listeners\AssignMemberRoleOnActivation;
use Modules\Memberships\Listeners\NotifyMembershipExpiring;
use Modules\Memberships\Listeners\RevokeMemberRoleOnExpiration;

final class MembershipsServiceProvider extends ModuleServiceProvider
{
    public function moduleName(): string
    {
        return 'memberships';
    }

    public function register(): void
    {
        parent::register();

        $this->forceLoadConfig($this->modulePath('config/memberships.php'), 'memberships');

        // Repository bindings
        $this->app->bind(MemberRepositoryInterface::class, EloquentMemberRepository::class);
        $this->app->bind(MembershipRepositoryInterface::class, EloquentMembershipRepository::class);
        $this->app->bind(MembershipFeeRepositoryInterface::class, EloquentMembershipFeeRepository::class);
        $this->app->bind(FeeStructureRepositoryInterface::class, EloquentFeeStructureRepository::class);

        // Service bindings
        $this->app->bind(MemberNumberGeneratorInterface::class, MemberNumberGenerator::class);
        $this->app->bind(ProrationCalculatorInterface::class, fn () => new ProrationCalculator(
            (int) config('memberships.academic_start_month', 9)
        ));
        $this->app->bind(MemberServiceInterface::class, MemberService::class);
        $this->app->bind(MembershipServiceInterface::class, MembershipService::class);
        $this->app->bind(MembershipFeeServiceInterface::class, MembershipFeeService::class);
        $this->app->bind(FeeStructureServiceInterface::class, FeeStructureService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerCommands();
        $this->registerSchedule();
        $this->registerEventListeners();
        $this->registerLivewireComponents();
    }

    /**
     * Register Livewire components from this module.
     */
    private function registerLivewireComponents(): void
    {
        Livewire::component(
            'modules.memberships.filament.relation-managers.memberships-relation-manager',
            \Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager::class
        );
    }

    /**
     * Register console commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessExpiredMemberships::class,
            ]);
        }
    }

    /**
     * Register scheduled tasks for this module.
     */
    private function registerSchedule(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // Process expired memberships daily at midnight
            $schedule->command('memberships:process-expired')->daily();
        });
    }

    /**
     * Register event listeners.
     */
    private function registerEventListeners(): void
    {
        Event::listen(
            MembershipActivated::class,
            [AssignMemberRoleOnActivation::class, 'handle']
        );

        Event::listen(
            MembershipExpired::class,
            [RevokeMemberRoleOnExpiration::class, 'handle']
        );

        Event::listen(
            MembershipExpiring::class,
            [NotifyMembershipExpiring::class, 'handle']
        );
    }

    public function onEnable(): void
    {
        // Migration is handled by the module system
    }

    public function onDisable(): void
    {
        // Cleanup if needed
    }

    /**
     * Register Filament resources provided by this module.
     *
     * @return array<class-string<\Filament\Resources\Resource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            \Modules\Memberships\Filament\Resources\MemberResource::class,
            \Modules\Memberships\Filament\Resources\FeeStructureResource::class,
        ];
    }

    /**
     * Register policies provided by this module.
     *
     * @return array<class-string, class-string>
     */
    public function registerPolicies(): array
    {
        return [
            \Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel::class => \Modules\Memberships\Policies\MemberPolicy::class,
            \Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel::class => \Modules\Memberships\Policies\FeeStructurePolicy::class,
        ];
    }

    /**
     * Register navigation groups provided by this module.
     *
     * @return array<string, array{icon?: string, sort?: int}>
     */
    public function registerNavigationGroups(): array
    {
        return [
            __('memberships::memberships.navigation_group') => [
                'sort' => 15, // After 'Contenido' (10), before 'Comunicación' (20)
            ],
        ];
    }

    /**
     * @return array<\App\Application\Modules\DTOs\PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            // Member permissions
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'members.view_any',
                label: __('memberships::memberships.permissions.members.view_any'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'members.view',
                label: __('memberships::memberships.permissions.members.view'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'members.create',
                label: __('memberships::memberships.permissions.members.create'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'members.update',
                label: __('memberships::memberships.permissions.members.update'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'members.delete',
                label: __('memberships::memberships.permissions.members.delete'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: [],
            ),
            // Fee structure permissions
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'fee_structures.view_any',
                label: __('memberships::memberships.permissions.fee_structures.view_any'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'fee_structures.view',
                label: __('memberships::memberships.permissions.fee_structures.view'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'fee_structures.create',
                label: __('memberships::memberships.permissions.fee_structures.create'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'fee_structures.update',
                label: __('memberships::memberships.permissions.fee_structures.update'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
            new \App\Application\Modules\DTOs\PermissionDTO(
                name: 'fee_structures.delete',
                label: __('memberships::memberships.permissions.fee_structures.delete'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
        ];
    }

    /**
     * @return array<\App\Application\Modules\DTOs\NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [
            new \App\Application\Modules\DTOs\NavigationItemDTO(
                label: __('memberships::memberships.navigation.members'),
                route: 'filament.admin.resources.members.index',
                icon: 'heroicon-o-user-group',
                group: __('memberships::memberships.navigation_group'),
                sort: 1,
                permissions: ['memberships:members.view_any'],
                module: 'memberships',
            ),
            new \App\Application\Modules\DTOs\NavigationItemDTO(
                label: __('memberships::memberships.navigation.fee_structures'),
                route: 'filament.admin.resources.fee-structures.index',
                icon: 'heroicon-o-currency-euro',
                group: __('memberships::memberships.navigation_group'),
                sort: 2,
                permissions: ['memberships:fee_structures.view_any'],
                module: 'memberships',
            ),
        ];
    }

    /**
     * Get the Filament form schema for module settings.
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return \Modules\Memberships\Filament\Pages\MembershipSettings::getFormSchemaComponents();
    }
}
