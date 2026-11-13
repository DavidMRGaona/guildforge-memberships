<?php

declare(strict_types=1);

namespace Modules\Memberships;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Modules\DTOs\NavigationItemDTO;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\DTOs\SlotRegistrationDTO;
use App\Filament\Support\PanelNavigationGroup;
use App\Modules\ModuleServiceProvider;
use Filament\Pages\Page;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Components\Component;
use Filament\Widgets\Widget;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Livewire\Livewire;
use Modules\Memberships\Application\DTOs\MemberSituationDTO;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Application\Services\MemberServiceInterface;
use Modules\Memberships\Application\Services\MembershipFeeServiceInterface;
use Modules\Memberships\Application\Services\MembershipServiceInterface;
use Modules\Memberships\Application\Services\MemberSituationQueryInterface;
use Modules\Memberships\Console\Commands\ProcessExpiredMemberships;
use Modules\Memberships\Console\Commands\SyncMemberRoles;
use Modules\Memberships\Domain\Events\FeePaymentOverdue;
use Modules\Memberships\Domain\Events\MembershipExpired;
use Modules\Memberships\Domain\Events\MembershipExpiring;
use Modules\Memberships\Domain\Repositories\FeeStructureRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Filament\RelationManagers\FeesRelationManager;
use Modules\Memberships\Filament\RelationManagers\MembershipsRelationManager;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Widgets\ExpiringMembershipsWidget;
use Modules\Memberships\Filament\Widgets\MembershipStatsWidget;
use Modules\Memberships\Filament\Widgets\OverdueFeesWidget;
use Modules\Memberships\Infrastructure\Knowledge\HasMemberRecordCapability;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers\FeeStructureModelObserver;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers\MemberModelObserver;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Observers\MembershipModelObserver;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentFeeStructureRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMemberRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMembershipFeeRepository;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Repositories\EloquentMembershipRepository;
use Modules\Memberships\Infrastructure\Services\MemberNumberGenerator;
use Modules\Memberships\Infrastructure\Services\MemberService;
use Modules\Memberships\Infrastructure\Services\MembershipFeeService;
use Modules\Memberships\Infrastructure\Services\MembershipService;
use Modules\Memberships\Infrastructure\Services\MemberSituationQuery;
use Modules\Memberships\Listeners\NotifyFeePaymentOverdue;
use Modules\Memberships\Listeners\NotifyMembershipExpired;
use Modules\Memberships\Listeners\NotifyMembershipExpiring;
use Modules\Memberships\Policies\FeeStructurePolicy;
use Modules\Memberships\Policies\MemberPolicy;
use Modules\Memberships\Policies\MembershipFeePolicy;

final class MembershipsServiceProvider extends ModuleServiceProvider
{
    /** Optional: reminder before the membership runs out. */
    public const string MAIL_EXPIRY_NOTICES = 'memberships.expiry-notices';

    /** Mandatory: the membership expired or a fee is overdue. */
    public const string MAIL_FEES = 'memberships.fees';

    /** The profile tab with the member's situation; the notices link to it (`?tab=`). */
    public const string PROFILE_TAB = 'memberships';

    private const string PROFILE_SITUATION_ATTRIBUTE = 'memberships.profile_situation';

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
        $this->app->bind(MemberServiceInterface::class, MemberService::class);
        $this->app->bind(MembershipServiceInterface::class, MembershipService::class);
        $this->app->bind(MembershipFeeServiceInterface::class, MembershipFeeService::class);
        $this->app->bind(MemberSituationQueryInterface::class, MemberSituationQuery::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerCommands();
        $this->registerSchedule();
        $this->registerEventListeners();
        $this->registerLivewireComponents();
        $this->shareProfileMembership();

        MembershipModel::observe(MembershipModelObserver::class);
        MemberModel::observe(MemberModelObserver::class);
        FeeStructureModel::observe(FeeStructureModelObserver::class);
    }

    /**
     * The profile's member tab: the situation of the record linked to the account, computed
     * only on the profile page and once per request. The tab hides itself for accounts without
     * a record; the badge counts the overdue fees.
     */
    private function shareProfileMembership(): void
    {
        // Kept on the request, not in the closure: a long-lived process serves many requests
        $resolve = static function (): ?MemberSituationDTO {
            $request = request();

            if (! $request->attributes->has(self::PROFILE_SITUATION_ATTRIBUTE)) {
                $user = auth()->user();
                $request->attributes->set(self::PROFILE_SITUATION_ATTRIBUTE, $request->route()?->getName() === 'profile.show' && $user !== null
                    ? app(MemberSituationQueryInterface::class)->forUser((string) $user->getAuthIdentifier())
                    : null);
            }

            $situation = $request->attributes->get(self::PROFILE_SITUATION_ATTRIBUTE);

            return $situation instanceof MemberSituationDTO ? $situation : null;
        };
        $onProfile = static fn (): bool => request()->route()?->getName() === 'profile.show' && auth()->user() !== null;

        Inertia::share('profileMembership', static fn (): ?array => $resolve()?->toArray());
        Inertia::share('profileMembershipOverdue', static fn (): ?int => $resolve()?->overdueCount());
        Inertia::share('profileMembershipVisible', static fn (): ?bool => $onProfile() ? $resolve() instanceof MemberSituationDTO : null);
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
        Livewire::component(
            'modules.memberships.filament.relation-managers.fees-relation-manager',
            FeesRelationManager::class
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

        // Both stay silent until enabled in the module settings
        Event::listen(
            MembershipExpired::class,
            [NotifyMembershipExpired::class, 'handle']
        );

        Event::listen(
            FeePaymentOverdue::class,
            [NotifyFeePaymentOverdue::class, 'handle']
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
     * The profile tab with the member's situation (read only).
     *
     * @return array<SlotRegistrationDTO>
     */
    public function registerSlots(): array
    {
        return [
            new SlotRegistrationDTO(
                slot: 'profile-sections',
                component: 'components/profile/ProfileMembershipSection.vue',
                module: $this->moduleName(),
                order: 50,
                // badgeKey and visibleKey are read from the slot's props, so they are listed too
                dataKeys: ['profileMembership', 'profileMembershipOverdue', 'profileMembershipVisible'],
                profileTab: [
                    'tabId' => self::PROFILE_TAB,
                    // The bell notices' icon; a core without it in its tab icons shows its neutral one
                    'icon' => 'id-card',
                    'labelKey' => 'memberships.profile.tabLabel',
                    'badgeKey' => 'profileMembershipOverdue',
                    'visibleKey' => 'profileMembershipVisible',
                ],
            ),
        ];
    }

    /**
     * Register Filament resources provided by this module.
     *
     * @return array<class-string<FilamentResource>>
     */
    public function registerFilamentResources(): array
    {
        return [
            MemberResource::class,
            FeeStructureResource::class,
        ];
    }

    /**
     * Register Filament pages provided by this module.
     *
     * @return array<class-string<Page>>
     */
    public function registerFilamentPages(): array
    {
        return [
            MembershipSettings::class,
        ];
    }

    /**
     * Register dashboard widgets provided by this module.
     *
     * @return array<class-string<Widget>>
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
            MembershipFeeModel::class => MembershipFeePolicy::class,
        ];
    }

    /**
     * Mail categories people see in their profile. Only accounts linked to a member record
     * see them: nobody else receives these mails.
     *
     * @return list<MailCategoryDTO>
     */
    public function registerMailCategories(): array
    {
        $hasMemberRecord = fn (object $user): bool => $this->app->make(HasMemberRecordCapability::class)->passes($user);

        return [
            MailCategoryDTO::optional(
                key: self::MAIL_EXPIRY_NOTICES,
                section: 'memberships::mail.categories.section',
                label: 'memberships::mail.categories.expiry_notices.label',
                description: 'memberships::mail.categories.expiry_notices.description',
                audience: $hasMemberRecord,
                site: true,
            ),
            MailCategoryDTO::mandatory(
                key: self::MAIL_FEES,
                section: 'memberships::mail.categories.section',
                label: 'memberships::mail.categories.fees.label',
                description: 'memberships::mail.categories.fees.description',
                reason: 'memberships::mail.categories.fees.reason',
                audience: $hasMemberRecord,
                site: true,
            ),
        ];
    }

    /**
     * Capabilities the module's help guides use to show only to whom they apply.
     *
     * @return array<class-string<KnowledgeCapabilityInterface>>
     */
    public function registerKnowledgeCapabilities(): array
    {
        return [
            HasMemberRecordCapability::class,
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
            ),
            new PermissionDTO(
                name: 'members.view',
                label: __('memberships::memberships.permissions.members.view'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
            ),
            new PermissionDTO(
                name: 'members.create',
                label: __('memberships::memberships.permissions.members.create'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
            ),
            new PermissionDTO(
                name: 'members.update',
                label: __('memberships::memberships.permissions.members.update'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
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
            ),
            new PermissionDTO(
                name: 'fee_structures.view',
                label: __('memberships::memberships.permissions.fee_structures.view'),
                group: __('memberships::memberships.navigation.fee_structures'),
                module: 'memberships',
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
            // Payment permissions: seeing them is not handling the money
            new PermissionDTO(
                name: 'fees.view_any',
                label: __('memberships::memberships.permissions.fees.view_any'),
                group: __('memberships::memberships.navigation.members'),
                module: 'memberships',
                roles: [],
            ),
            new PermissionDTO(
                name: 'fees.manage',
                label: __('memberships::memberships.permissions.fees.manage'),
                group: __('memberships::memberships.navigation.members'),
                description: __('memberships::memberships.permissions.fees.manage_help'),
                module: 'memberships',
                roles: [],
            ),
            // Settings permission: decides who gets the association member role
            new PermissionDTO(
                name: 'settings.manage',
                label: __('memberships::memberships.permissions.settings.manage'),
                group: __('memberships::memberships.permissions.settings.group'),
                description: __('memberships::memberships.permissions.settings.manage_help'),
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
                group: PanelNavigationGroup::People->getLabel(),
                sort: 30,
                permissions: ['memberships:members.view_any'],
                module: 'memberships',
            ),
            new NavigationItemDTO(
                label: __('memberships::memberships.navigation.fee_structures'),
                route: 'filament.admin.resources.fee-structures.index',
                icon: 'heroicon-o-currency-euro',
                group: PanelNavigationGroup::People->getLabel(),
                sort: 31,
                permissions: ['memberships:fee_structures.view_any'],
                module: 'memberships',
            ),
        ];
    }

    /**
     * Get the Filament form schema for module settings.
     *
     * @return array<Component>
     */
    public function getSettingsSchema(): array
    {
        return MembershipSettings::getFormSchemaComponents();
    }
}
