<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Services;

use Modules\Memberships\Application\DTOs\FeeStructureDTO;
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
        $service = $this->service();
        $fee = $this->createFee();

        $updated = $service->update($fee->id, amountCents: 3500);

        $this->assertSame(3500, $updated->amountCents);
        $this->assertSame('EUR', $updated->currency);
    }

    public function test_an_update_saves_the_description_and_the_proration_rules(): void
    {
        $service = $this->service();
        $fee = $this->createFee();

        $service->update(
            $fee->id,
            amountCents: 3500,
            description: 'New description',
            prorationRules: ['monthly' => 300],
        );

        $stored = $service->findOrFail($fee->id);
        $this->assertSame(3500, $stored->amountCents);
        $this->assertSame('New description', $stored->description);
        $this->assertSame(['monthly' => 300], $stored->prorationRules);
    }

    public function test_an_update_that_does_not_name_the_description_or_the_proration_rules_keeps_them(): void
    {
        $service = $this->service();
        $fee = $this->createFee(description: 'Original description', prorationRules: ['monthly' => 250]);

        $service->update($fee->id, amountCents: 3500);

        $stored = $service->findOrFail($fee->id);
        $this->assertSame('Original description', $stored->description);
        $this->assertSame(['monthly' => 250], $stored->prorationRules);
    }

    /**
     * @param  array<string, mixed>|null  $prorationRules
     */
    private function createFee(?string $description = null, ?array $prorationRules = null): FeeStructureDTO
    {
        return $this->service()->create(
            memberType: MemberType::Regular->value,
            periodType: MembershipPeriodType::CalendarYear->value,
            amountCents: 3000,
            currency: 'EUR',
            validFrom: '2026-01-01',
            description: $description,
            prorationRules: $prorationRules,
        );
    }

    private function service(): FeeStructureServiceInterface
    {
        return $this->app->make(FeeStructureServiceInterface::class);
    }
}
