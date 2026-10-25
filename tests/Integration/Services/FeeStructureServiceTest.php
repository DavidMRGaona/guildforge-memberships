<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use Modules\Memberships\Application\Services\FeeStructureServiceInterface;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Tests\Support\Modules\ModuleTestCase;

final class FeeStructureServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    public function test_changing_the_amount_keeps_the_currency(): void
    {
        $service = $this->app->make(FeeStructureServiceInterface::class);
        $fee = $service->create(
            memberType: MemberType::Regular->value,
            periodType: MembershipPeriodType::CalendarYear->value,
            amountCents: 3000,
            currency: 'EUR',
            validFrom: '2026-01-01',
        );

        $updated = $service->update($fee->id, amountCents: 3500);

        $this->assertSame(3500, $updated->amountCents);
        $this->assertSame('EUR', $updated->currency);
    }
}
