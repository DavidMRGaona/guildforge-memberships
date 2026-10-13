<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit;

use App\Application\Modules\DTOs\PermissionDTO;
use Modules\Memberships\MembershipsServiceProvider;
use Tests\TestCase;

final class ModuleManifestTest extends TestCase
{
    public function test_declared_permissions_match_the_registered_ones(): void
    {
        /** @var array{provides: array{permissions: list<string>}} $manifest */
        $manifest = json_decode((string) file_get_contents(__DIR__.'/../../module.json'), true, flags: JSON_THROW_ON_ERROR);

        $registered = array_map(
            fn (PermissionDTO $permission): string => $permission->name,
            (new MembershipsServiceProvider($this->app))->registerPermissions(),
        );

        $declared = $manifest['provides']['permissions'];
        sort($declared);
        sort($registered);

        $this->assertSame($registered, $declared);
    }
}
