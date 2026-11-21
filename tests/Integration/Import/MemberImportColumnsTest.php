<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Import;

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowReportDTO;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MemberImportRowStatus;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\ModuleTestCase;

/**
 * How each column of a row is read. Rows come as a spreadsheet reader hands them over
 * (header → text, with the line of the file); "today" is 15 June 2026 in the display zone.
 */
final class MemberImportColumnsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $zone = $this->app->make(TimezoneServiceInterface::class)->displayTimezone();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00', $zone));
    }

    public function test_a_complete_row_is_stored_with_every_value(): void
    {
        $result = $this->import([[
            'Nombre' => '  Ana ',
            'Apellidos' => 'Pérez Gil',
            'Email' => 'ana@example.test',
            'Teléfono' => '600 000 001',
            'Fecha de nacimiento' => '17/05/1990',
            'Dirección' => 'Calle Ejemplo 1',
            'Tipo de socio' => 'Estudiante',
            'Fecha de alta' => '2024-03-01',
            'Número de socio' => '2024-0007',
            'Notas' => 'Viene de la hoja antigua',
            'Tipo de periodo' => 'Año natural',
            'Inicio del periodo' => '01/01/2026',
            'Fin del periodo' => '31/12/2026',
            'Importe' => '25,50',
            'Vencimiento' => '31/01/2026',
            'Pagada el' => '15/01/2026',
            'Forma de pago' => 'Transferencia bancaria',
            'Referencia' => 'TR-001',
        ]]);

        $this->assertTrue($result->imported);
        $this->assertSame(1, $result->createdCount);

        $member = MemberModel::query()->sole();
        $this->assertSame('Ana', $member->first_name);
        $this->assertSame('Pérez Gil', $member->last_name);
        $this->assertSame('ana@example.test', $member->email);
        $this->assertSame('600 000 001', $member->phone);
        $this->assertSame('1990-05-17', $member->birth_date?->toDateString());
        $this->assertSame('Calle Ejemplo 1', $member->address);
        $this->assertSame('student', $member->member_type->value);
        $this->assertSame('active', $member->status->value);
        $this->assertSame('2024-03-01', $this->displayDay($member->joined_at));
        $this->assertSame('2024-0007', $member->member_number);
        $this->assertSame('Viene de la hoja antigua', $member->notes);
        $this->assertNull($member->user_id);

        $membership = MembershipModel::query()->sole();
        $this->assertSame($member->id, $membership->member_id);
        $this->assertSame('calendar_year', $membership->period_type->value);
        $this->assertSame('2026-01-01', $membership->start_date->toDateString());
        $this->assertSame('2026-12-31', $membership->end_date->toDateString());
        $this->assertSame('active', $membership->status->value);

        $fee = MembershipFeeModel::query()->sole();
        $this->assertSame($membership->id, $fee->membership_id);
        $this->assertSame(25.5, (float) $fee->amount);
        $this->assertSame('EUR', $fee->currency);
        $this->assertSame('2026-01-31', $fee->due_date->toDateString());
        $this->assertNotNull($fee->paid_at);
        $this->assertSame('2026-01-15', $this->displayDay($fee->paid_at));
        $this->assertSame('bank_transfer', $fee->payment_method?->value);
        $this->assertSame('TR-001', $fee->transaction_reference);
    }

    public function test_only_names_are_needed_and_the_rest_takes_its_default(): void
    {
        $this->import([['Nombre' => 'Ana', 'Apellidos' => 'Pérez']]);

        $member = MemberModel::query()->sole();
        $this->assertSame('regular', $member->member_type->value);
        $this->assertSame('2026-06-15', $this->displayDay($member->joined_at));
        $this->assertNull($member->email);
        $this->assertSame(0, MembershipModel::query()->count());
    }

    public function test_headers_are_read_without_case_accents_or_extra_spaces(): void
    {
        $row = $this->onlyRow($this->preview([[
            'NOMBRE' => 'Ana',
            ' apellidos ' => 'Pérez',
            'numero  de socio' => '2024-0001',
            'TELEFONO' => '600',
        ]]));

        $this->assertSame(MemberImportRowStatus::New, $row->status);
        $this->assertSame('2024-0001', $row->memberNumber);
    }

    public function test_column_codes_are_accepted_as_headers(): void
    {
        $this->import([['first_name' => 'Ana', 'last_name' => 'Pérez', 'member_number' => '2024-0003']]);

        $this->assertSame('2024-0003', MemberModel::query()->sole()->member_number);
    }

    public function test_unknown_headers_are_reported_and_ignored(): void
    {
        $preview = $this->preview([$this->row(['Correo' => 'ana@example.test', 'Mote' => 'Anita'])]);

        $this->assertSame(['Correo', 'Mote'], $preview->unknownColumns);
        $this->assertSame(MemberImportRowStatus::New, $this->onlyRow($preview)->status);
        $this->assertTrue($preview->canImport());
    }

    public function test_two_headers_for_the_same_column_block_the_import(): void
    {
        $result = $this->import([$this->row(['Email' => 'ana@example.test', 'EMAIL' => 'otra@example.test'])]);

        $this->assertSame(['EMAIL'], $result->report->repeatedColumns);
        $this->assertFalse($result->report->canImport());
        $this->assertFalse($result->imported);
        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_first_and_last_name_are_required(): void
    {
        $row = $this->onlyRow($this->preview([['Nombre' => ' ', 'Email' => 'ana@example.test']]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::FirstName->value, $row->errors);
        $this->assertArrayHasKey(MemberImportColumn::LastName->value, $row->errors);
    }

    public function test_a_name_longer_than_the_column_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Nombre' => str_repeat('a', 256)])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::FirstName->value, $row->errors);
    }

    public function test_a_malformed_email_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Email' => 'ana@'])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::Email->value, $row->errors);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validDates(): array
    {
        return [
            'day first' => ['05/03/2024', '2024-03-05'],
            'day first without zeros' => ['5/3/2024', '2024-03-05'],
            'ISO' => ['2024-03-05', '2024-03-05'],
        ];
    }

    #[DataProvider('validDates')]
    public function test_dates_are_read_day_first_or_iso(string $text, string $day): void
    {
        $this->import([$this->row(['Fecha de alta' => $text])]);

        $this->assertSame($day, $this->displayDay(MemberModel::query()->sole()->joined_at));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'not a day' => ['31/02/2024'],
            'slashes year first' => ['2024/03/05'],
            'month first words' => ['March 5, 2024'],
            'two digit year' => ['05/03/24'],
            'text' => ['ayer'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_other_date_formats_are_invalid(string $text): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Fecha de alta' => $text])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::JoinedAt->value, $row->errors);
    }

    public function test_a_birth_date_in_the_future_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Fecha de nacimiento' => '16/06/2026'])]));

        $this->assertArrayHasKey(MemberImportColumn::BirthDate->value, $row->errors);
    }

    public function test_a_joining_date_in_the_future_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Fecha de alta' => '16/06/2026'])]));

        $this->assertArrayHasKey(MemberImportColumn::JoinedAt->value, $row->errors);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function memberTypes(): array
    {
        return [
            'label' => ['Honorario', 'honorary'],
            'label without accent or case' => ['FUNDADOR', 'founder'],
            'code' => ['senior', 'senior'],
            'empty' => ['', 'regular'],
        ];
    }

    #[DataProvider('memberTypes')]
    public function test_member_types_are_read_by_name_or_code(string $text, string $type): void
    {
        $this->import([$this->row(['Tipo de socio' => $text])]);

        $this->assertSame($type, MemberModel::query()->sole()->member_type->value);
    }

    public function test_an_unknown_member_type_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Tipo de socio' => 'Vitalicio'])]));

        $this->assertArrayHasKey(MemberImportColumn::MemberType->value, $row->errors);
    }

    public function test_period_types_and_payment_methods_are_read_by_name_or_code(): void
    {
        $this->import([$this->row([
            'Tipo de periodo' => 'rolling',
            'Inicio del periodo' => '01/03/2026',
            'Importe' => '10',
            'Pagada el' => '01/03/2026',
            'Forma de pago' => 'efectivo',
        ])]);

        $this->assertSame('rolling', MembershipModel::query()->sole()->period_type->value);
        $this->assertSame('cash', MembershipFeeModel::query()->sole()->payment_method?->value);
    }

    public function test_unknown_period_types_and_payment_methods_are_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row([
            'Tipo de periodo' => 'Trimestral',
            'Inicio del periodo' => '01/03/2026',
            'Importe' => '10',
            'Pagada el' => '01/03/2026',
            'Forma de pago' => 'Bizum',
        ])]));

        $this->assertArrayHasKey(MemberImportColumn::PeriodType->value, $row->errors);
        $this->assertArrayHasKey(MemberImportColumn::PaymentMethod->value, $row->errors);
    }

    /**
     * @return array<string, array{string, float}>
     */
    public static function amounts(): array
    {
        return [
            'comma' => ['25,00', 25.0],
            'point' => ['25.00', 25.0],
            'whole' => ['25', 25.0],
            'one decimal from a spreadsheet' => ['25.5', 25.5],
            'with the euro sign' => ['12,30 €', 12.3],
            'zero' => ['0', 0.0],
        ];
    }

    #[DataProvider('amounts')]
    public function test_amounts_are_read_with_comma_or_point(string $text, float $amount): void
    {
        $this->import([$this->row(['Inicio del periodo' => '01/01/2026', 'Importe' => $text])]);

        $this->assertSame($amount, (float) MembershipFeeModel::query()->sole()->amount);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'negative' => ['-5'],
            'thousands separator' => ['1.234,56'],
            'three decimals' => ['2,505'],
            'text' => ['veinte'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_negative_or_malformed_amounts_are_invalid(string $text): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Inicio del periodo' => '01/01/2026', 'Importe' => $text])]));

        $this->assertArrayHasKey(MemberImportColumn::FeeAmount->value, $row->errors);
    }

    public function test_a_fee_needs_a_period(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Importe' => '25'])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::FeeAmount->value, $row->errors);
    }

    public function test_a_fee_needs_its_amount(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Inicio del periodo' => '01/01/2026', 'Vencimiento' => '31/01/2026'])]));

        $this->assertArrayHasKey(MemberImportColumn::FeeAmount->value, $row->errors);
    }

    public function test_a_payment_method_or_reference_needs_the_payment_date(): void
    {
        $row = $this->onlyRow($this->preview([$this->row([
            'Inicio del periodo' => '01/01/2026',
            'Importe' => '25',
            'Forma de pago' => 'Efectivo',
        ])]));

        $this->assertArrayHasKey(MemberImportColumn::FeePaidAt->value, $row->errors);
    }

    public function test_a_payment_date_in_the_future_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row([
            'Inicio del periodo' => '01/01/2026',
            'Importe' => '25',
            'Pagada el' => '16/06/2026',
        ])]));

        $this->assertArrayHasKey(MemberImportColumn::FeePaidAt->value, $row->errors);
    }

    public function test_an_unpaid_fee_is_due_on_the_first_day_of_the_period_by_default(): void
    {
        $this->import([$this->row(['Inicio del periodo' => '01/02/2026', 'Importe' => '25'])]);

        $fee = MembershipFeeModel::query()->sole();
        $this->assertSame('2026-02-01', $fee->due_date->toDateString());
        $this->assertNull($fee->paid_at);
        $this->assertNull($fee->payment_method);
    }

    public function test_a_period_cannot_end_before_it_starts(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Inicio del periodo' => '01/03/2026', 'Fin del periodo' => '28/02/2026'])]));

        $this->assertArrayHasKey(MemberImportColumn::PeriodEnd->value, $row->errors);
    }

    public function test_a_member_number_with_another_format_is_invalid(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Número de socio' => '17'])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::MemberNumber->value, $row->errors);
    }

    public function test_errors_are_translated_messages(): void
    {
        $row = $this->onlyRow($this->preview([['Nombre' => 'Ana']]));

        $message = $row->errors[MemberImportColumn::LastName->value][0] ?? '';
        $this->assertNotSame('', $message);
        $this->assertStringNotContainsString('memberships::', $message);
    }

    public function test_every_column_has_a_spanish_and_an_english_header(): void
    {
        foreach (['es', 'en'] as $locale) {
            $translations = require __DIR__."/../../../lang/{$locale}/memberships.php";
            $this->assertIsArray($translations);

            foreach (MemberImportColumn::cases() as $column) {
                $this->assertNotSame('', trim((string) ($translations['import']['columns'][$column->value] ?? '')), "{$locale}: {$column->value}");
            }
        }
    }

    private function displayDay(CarbonInterface $instant): string
    {
        $zone = $this->app->make(TimezoneServiceInterface::class)->displayTimezone();

        return $instant->toDateTimeImmutable()->setTimezone($zone)->format('Y-m-d');
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function preview(array $rows): MemberImportPreviewDTO
    {
        return $this->app->make(MemberImportServiceInterface::class)->preview($this->rows($rows), new MemberImportOptionsDTO);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function import(array $rows): MemberImportResultDTO
    {
        return $this->app->make(MemberImportServiceInterface::class)->import($this->rows($rows), new MemberImportOptionsDTO);
    }

    /**
     * Lines start at 2: the header is the first line of the file.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<MemberImportRowDTO>
     */
    private function rows(array $rows): array
    {
        $lines = [];

        foreach ($rows as $index => $values) {
            $lines[] = new MemberImportRowDTO(line: $index + 2, values: $values);
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function row(array $extra = []): array
    {
        return array_merge(['Nombre' => 'Ana', 'Apellidos' => 'Pérez'], $extra);
    }

    private function onlyRow(MemberImportPreviewDTO $preview): MemberImportRowReportDTO
    {
        $this->assertCount(1, $preview->rows);

        return $preview->rows[0];
    }
}
