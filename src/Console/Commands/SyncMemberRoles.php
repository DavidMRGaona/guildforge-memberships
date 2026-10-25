<?php

declare(strict_types=1);

namespace Modules\Memberships\Console\Commands;

use Illuminate\Console\Command;
use Modules\Memberships\Application\DTOs\RoleSyncSummaryDTO;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final class SyncMemberRoles extends Command
{
    /**
     * @var string
     */
    protected $signature = 'memberships:sync-roles
        {--dry-run : Report the changes without granting or removing any role}';

    /**
     * @var string
     */
    protected $description = 'Grant the association member role to the linked users that qualify and remove it from the other users linked to a member';

    public function __construct(
        private readonly MemberRoleSynchronizer $roles,
        private readonly MembershipSettingsReader $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->settings->roleAssignmentEnabled()) {
            $this->components->error(__('memberships::memberships.commands.sync_roles.disabled'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $summary = $this->roles->syncAll($dryRun);

        if (! $summary instanceof RoleSyncSummaryDTO) {
            $this->components->error(__('memberships::memberships.commands.sync_roles.role_unavailable'));

            return self::FAILURE;
        }

        $this->components->info(__('memberships::memberships.commands.sync_roles.summary', [
            'checked' => $summary->checked,
            'added' => $summary->added,
            'removed' => $summary->removed,
        ]));

        if ($dryRun) {
            $this->components->warn(__('memberships::memberships.commands.sync_roles.dry_run'));
        }

        return self::SUCCESS;
    }
}
