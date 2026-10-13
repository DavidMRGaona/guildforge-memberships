<?php

declare(strict_types=1);

namespace Modules\Memberships\Console\Commands;

use Illuminate\Console\Command;
use Modules\Memberships\Infrastructure\Services\MemberRoleSynchronizer;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

final class SyncMemberRoles extends Command
{
    /**
     * @var string
     */
    protected $signature = 'memberships:sync-roles';

    /**
     * @var string
     */
    protected $description = 'Grant the member role to users with an active membership and remove it from the other users linked to a member';

    public function __construct(
        private readonly MemberRoleSynchronizer $roles,
        private readonly MembershipSettingsReader $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->settings->roleAssignmentEnabled()) {
            $this->components->error('Role assignment is disabled in the module settings: nothing was changed.');

            return self::FAILURE;
        }

        $count = $this->roles->syncAll();

        $this->components->info("Member role synchronised for {$count} linked users.");

        return self::SUCCESS;
    }
}
