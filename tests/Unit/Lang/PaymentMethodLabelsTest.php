<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Unit\Lang;

use Modules\Memberships\Domain\Enums\PaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentMethodLabelsTest extends TestCase
{
    private const string MODULE_PATH = __DIR__.'/../../..';

    /**
     * @return array<string, array{string}>
     */
    public static function locales(): array
    {
        return ['es' => ['es'], 'en' => ['en']];
    }

    #[DataProvider('locales')]
    public function test_every_payment_method_value_has_a_translation(string $locale): void
    {
        $labels = $this->paymentMethodLabels($locale);

        foreach (PaymentMethod::cases() as $method) {
            $this->assertArrayHasKey($method->value, $labels, "Missing {$locale} label for {$method->value}");
            $this->assertNotSame('', trim($labels[$method->value]));
        }
    }

    #[DataProvider('locales')]
    public function test_translations_have_no_key_without_an_enum_case(string $locale): void
    {
        $this->assertEqualsCanonicalizing(
            PaymentMethod::values(),
            array_keys($this->paymentMethodLabels($locale)),
        );
    }

    public function test_card_is_labelled_as_card(): void
    {
        $this->assertSame('Tarjeta', $this->paymentMethodLabels('es')['card'] ?? null);
        $this->assertSame('Card', $this->paymentMethodLabels('en')['card'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function paymentMethodLabels(string $locale): array
    {
        $translations = require self::MODULE_PATH."/lang/{$locale}/memberships.php";
        $this->assertIsArray($translations);
        $labels = $translations['enums']['payment_method'] ?? [];
        $this->assertIsArray($labels);

        return $labels;
    }
}
