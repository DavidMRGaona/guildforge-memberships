<?php

declare(strict_types=1);

namespace Modules\Memberships\Application\DTOs;

/**
 * Outcome of synchronising the association member role of every linked user.
 */
final readonly class RoleSyncSummaryDTO
{
    public function __construct(
        public int $checked,
        public int $added,
        public int $removed,
    ) {
    }
}
