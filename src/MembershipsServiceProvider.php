<?php

declare(strict_types=1);

namespace Modules\Memberships;

use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Policies\MemberPolicy;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Policies\FeeStructurePolicy;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Filament\Resources\Resource;
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
use Modules\Memberships\Console\Commands\SyncMemberRoles;
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
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;
use Modules\Memberships\Infrastructure\Services\ProrationCalculator;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers\MemberModelObserver;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers\MembershipModelObserver;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\MembershipStatsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Listeners\NotifyMembershipExpiring;

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
        $this->app->bind(ProrationCalculatorInterface::class, fn (): ProrationCalculator => new ProrationCalculator(
            $this->app->make(MembershipSettingsReader::class)->academicStartMonth()
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

        MembershipModel::observe(MembershipModelObserver::class);
        MemberModel::observe(MemberModelObserver::class);
    }

    /**
     * Register Livewire components from this module.
     */
    private function registerLivewireComponents(): void
    {
        Livewire::component(
            'modules.memberships.filament.relation-managers.memberships-relation-manager',
            MembershipsRelationManager::class
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
                SyncMemberRoles::class,
            ]);
        }
    }

    /**
     * Register scheduled tasks for this module.
     */
    private function registerSchedule(): void
    {
        // Only when the scheduler is resolved, so web requests do not build it
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
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
     * @return array<class-string<Resource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            MemberResource::class,
            FeeStructureResource::class,
        ];
    }

    /**
     * Register dashboard widgets provided by this module.
     *
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    public function registerFilamentWidgets(): array
    {
        return [
            MembershipStatsWidget::class,
            ExpiringMembershipsWidget::class,
            OverdueFeesWidget::class,
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
            MemberModel::class => MemberPolicy::class,
            FeeStructureModel::class => FeeStructurePolicy::class,
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
     * @return array<PermissionDTO>
     */
    public function registerPermissions(): array
    {
        return [
            // Member permissions
            new PermissionDTO(
                name: 'members.view_any',
                label: __('memberships::memberships.permissions.members.view_any'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'members.view',
                label: __('memberships::memberships.permissions.members.view'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'members.create',
                label: __('memberships::memberships.permissions.members.create'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'members.update',
                label: __('memberships::memberships.permissions.members.update'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'members.delete',
                label: __('memberships::memberships.permissions.members.delete'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: [],
            ),
            // Fee structure permissions
            new PermissionDTO(
                name: 'fee_structures.view_any',
                label: __('memberships::memberships.permissions.fee_structures.view_any'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'fee_structures.view',
                label: __('memberships::memberships.permissions.fee_structures.view'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: ['editor'],
            ),
            new PermissionDTO(
                name: 'fee_structures.create',
                label: __('memberships::memberships.permissions.fee_structures.create'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
            new PermissionDTO(
                name: 'fee_structures.update',
                label: __('memberships::memberships.permissions.fee_structures.update'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
            new PermissionDTO(
                name: 'fee_structures.delete',
                label: __('memberships::memberships.permissions.fee_structures.delete'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
                roles: [],
            ),
        ];
    }

    /**
     * @return array<NavigationItemDTO>
     */
    public function registerNavigation(): array
    {
        return [
            new NavigationItemDTO(
                label: __('memberships::memberships.navigation.members'),
                route: 'filament.admin.resources.members.index',
                icon: 'heroicon-o-user-group',
                group: __('memberships::memberships.navigation_group'),
                sort: 1,
                permissions: ['memberships:members.view_any'],
                module: 'memberships',
            ),
            new NavigationItemDTO(
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
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function getSettingsSchema(): array
    {
        return MembershipSettings::getFormSchemaComponents();
    }
}
