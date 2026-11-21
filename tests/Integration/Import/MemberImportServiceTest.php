<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Integration\Import;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowReportDTO;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MemberImportDuplicateReason;
use Modules\Memberships\Domain\Enums\MemberImportLinkSkipReason;
use Modules\Memberships\Domain\Enums\MemberImportRowStatus;
use Modules\Memberships\Domain\Exceptions\MemberImportFailedException;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\FeeStructureModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipFeeModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\Import\MemberImportService;
use RuntimeException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * What the import decides for each row and what it creates. "Today" is 15 June 2026 in the
 * display zone.
 */
final class MemberImportServiceTest extends ModuleTestCase
{
    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $zone = $this->app->make(TimezoneServiceInterface::class)->displayTimezone();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00', $zone));
    }

    // Member numbers

    public function test_a_free_member_number_in_the_file_is_kept(): void
    {
        $this->import([$this->row(['Número de socio' => '2019-0042'])]);

        $this->assertSame('2019-0042', MemberModel::query()->sole()->member_number);
    }

    public function test_a_missing_number_is_generated_from_the_joining_year(): void
    {
        $this->existingMember(['member_number' => '2024-0001']);
        $this->existingMember(['member_number' => '2024-0002'])->delete();

        $this->import([
            $this->row(['Email' => 'a@example.test', 'Fecha de alta' => '10/02/2024']),
            $this->row(['Email' => 'b@example.test', 'Fecha de alta' => '01/09/2024']),
            $this->row(['Email' => 'c@example.test', 'Fecha de alta' => '01/09/2025']),
        ]);

        $this->assertSame('2024-0003', $this->numberOf('a@example.test'));
        $this->assertSame('2024-0004', $this->numberOf('b@example.test'));
        $this->assertSame('2025-0001', $this->numberOf('c@example.test'));
    }

    public function test_generated_numbers_skip_the_numbers_given_in_the_same_file(): void
    {
        $this->import([
            $this->row(['Email' => 'a@example.test', 'Fecha de alta' => '10/02/2024']),
            $this->row(['Email' => 'b@example.test', 'Número de socio' => '2024-0001']),
            $this->row(['Email' => 'c@example.test', 'Fecha de alta' => '10/02/2024']),
        ]);

        $this->assertSame('2024-0002', $this->numberOf('a@example.test'));
        $this->assertSame('2024-0001', $this->numberOf('b@example.test'));
        $this->assertSame('2024-0003', $this->numberOf('c@example.test'));
    }

    public function test_the_preview_shows_the_numbers_the_import_gives(): void
    {
        $rows = [
            $this->row(['Email' => 'a@example.test', 'Fecha de alta' => '10/02/2024']),
            $this->row(['Email' => 'b@example.test', 'Fecha de alta' => '10/02/2024']),
        ];

        $preview = $this->preview($rows);
        $this->import($rows);

        $this->assertSame(['2024-0001', '2024-0002'], array_map(
            static fn ($row): ?string => $row->memberNumber,
            $preview->rows,
        ));
        $this->assertSame('2024-0001', $this->numberOf('a@example.test'));
        $this->assertSame('2024-0002', $this->numberOf('b@example.test'));
    }

    public function test_a_year_without_free_numbers_makes_the_row_invalid(): void
    {
        $this->existingMember(['member_number' => '2024-9999']);

        $row = $this->onlyRow($this->preview([$this->row(['Fecha de alta' => '10/02/2024'])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::MemberNumber->value, $row->errors);
        $this->assertStringContainsString('2024', $row->errors[MemberImportColumn::MemberNumber->value][0]);
    }

    public function test_numbers_given_later_go_on_after_the_imported_ones(): void
    {
        $this->import([$this->row(['Fecha de alta' => '10/02/2026']), $this->row(['Fecha de alta' => '11/02/2026'])]);

        $this->assertSame('2026-0003', $this->app->make(MemberNumberGeneratorInterface::class)->generate()->value);
    }

    // Duplicates

    public function test_a_row_whose_number_already_exists_is_skipped_and_the_member_is_not_updated(): void
    {
        $existing = $this->existingMember(['member_number' => '2024-0001', 'first_name' => 'Luis']);

        $result = $this->import([
            $this->row(['Nombre' => 'Otro', 'Número de socio' => '2024-0001']),
            $this->row(['Email' => 'nueva@example.test']),
        ]);

        $this->assertTrue($result->imported);
        $duplicate = $result->report->rows[0];
        $this->assertSame(MemberImportRowStatus::Duplicate, $duplicate->status);
        $this->assertSame(MemberImportDuplicateReason::MemberNumber, $duplicate->duplicateReason);
        $this->assertSame(1, $result->report->duplicateCount());
        $this->assertSame(1, $result->createdCount);
        $this->assertSame('Luis', $existing->fresh()?->first_name);
        $this->assertSame(2, MemberModel::query()->count());
    }

    public function test_a_row_whose_email_already_exists_is_skipped_whatever_the_case(): void
    {
        $this->existingMember(['email' => 'Ana@Example.test']);

        $row = $this->onlyRow($this->preview([$this->row(['Email' => 'ana@example.TEST'])]));

        $this->assertSame(MemberImportRowStatus::Duplicate, $row->status);
        $this->assertSame(MemberImportDuplicateReason::Email, $row->duplicateReason);
    }

    public function test_deleted_members_count_as_duplicates(): void
    {
        $this->existingMember(['member_number' => '2024-0001'])->delete();
        $this->existingMember(['member_number' => '2024-0002', 'email' => 'ana@example.test'])->delete();

        $preview = $this->preview([
            $this->row(['Número de socio' => '2024-0001']),
            $this->row(['Email' => 'ana@example.test']),
        ]);

        $this->assertSame(MemberImportDuplicateReason::MemberNumber, $preview->rows[0]->duplicateReason);
        $this->assertSame(MemberImportDuplicateReason::Email, $preview->rows[1]->duplicateReason);
    }

    public function test_a_duplicate_row_is_skipped_even_with_other_mistakes(): void
    {
        $this->existingMember(['email' => 'ana@example.test']);

        $result = $this->import([$this->row(['Email' => 'ana@example.test', 'Fecha de alta' => 'ayer'])]);

        $this->assertSame(MemberImportRowStatus::Duplicate, $result->report->rows[0]->status);
        // Nothing new to create
        $this->assertFalse($result->imported);
        $this->assertSame(0, $result->report->invalidCount());
    }

    public function test_two_rows_of_the_file_with_the_same_number_or_email_are_both_invalid(): void
    {
        $preview = $this->preview([
            $this->row(['Número de socio' => '2024-0001', 'Email' => 'a@example.test']),
            $this->row(['Número de socio' => '2024-0001', 'Email' => 'b@example.test']),
            $this->row(['Email' => 'C@example.test']),
            $this->row(['Email' => 'c@example.test']),
            $this->row(['Email' => 'd@example.test']),
        ]);

        foreach ([0, 1] as $index) {
            $this->assertSame(MemberImportRowStatus::Invalid, $preview->rows[$index]->status);
            $this->assertArrayHasKey(MemberImportColumn::MemberNumber->value, $preview->rows[$index]->errors);
        }

        foreach ([2, 3] as $index) {
            $this->assertSame(MemberImportRowStatus::Invalid, $preview->rows[$index]->status);
            $this->assertArrayHasKey(MemberImportColumn::Email->value, $preview->rows[$index]->errors);
        }

        $this->assertStringContainsString('4', $preview->rows[2]->errors[MemberImportColumn::Email->value][0]);
        $this->assertSame(MemberImportRowStatus::New, $preview->rows[4]->status);
        $this->assertSame(4, $preview->invalidCount());
        $this->assertSame(1, $preview->newCount());
    }

    public function test_a_new_row_sharing_its_email_with_a_skipped_row_is_invalid(): void
    {
        $this->existingMember(['member_number' => '2024-0001']);

        $preview = $this->preview([
            $this->row(['Número de socio' => '2024-0001', 'Email' => 'ana@example.test']),
            $this->row(['Email' => 'ana@example.test']),
        ]);

        $this->assertSame(MemberImportRowStatus::Duplicate, $preview->rows[0]->status);
        $this->assertSame(MemberImportRowStatus::Invalid, $preview->rows[1]->status);
        $this->assertArrayHasKey(MemberImportColumn::Email->value, $preview->rows[1]->errors);
    }

    public function test_a_row_that_became_a_duplicate_after_the_preview_is_skipped(): void
    {
        $rows = [$this->row(['Email' => 'ana@example.test'])];
        $this->assertSame(MemberImportRowStatus::New, $this->preview($rows)->rows[0]->status);

        $this->existingMember(['email' => 'ana@example.test']);
        $result = $this->import($rows);

        $this->assertSame(MemberImportRowStatus::Duplicate, $result->report->rows[0]->status);
        $this->assertFalse($result->imported);
        $this->assertSame(1, MemberModel::query()->count());
    }

    public function test_importing_the_same_file_again_creates_nothing(): void
    {
        $rows = [
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/01/2026', 'Importe' => '25']),
            $this->row(['Email' => 'b@example.test', 'Número de socio' => '2020-0001']),
        ];

        $this->import($rows);
        $again = $this->import($rows);

        $this->assertFalse($again->imported);
        $this->assertFalse($again->report->canImport());
        $this->assertSame(0, $again->createdCount);
        $this->assertSame(2, $again->report->duplicateCount());
        $this->assertSame(2, MemberModel::query()->count());
        $this->assertSame(1, MembershipModel::query()->count());
        $this->assertSame(1, MembershipFeeModel::query()->count());
    }

    // Periods

    public function test_a_period_that_already_ended_is_expired_and_one_still_running_is_active(): void
    {
        $this->import([
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/01/2025', 'Fin del periodo' => '31/12/2025']),
            $this->row(['Email' => 'b@example.test', 'Tipo de periodo' => 'Rotativo', 'Inicio del periodo' => '01/01/2026', 'Fin del periodo' => '15/06/2026']),
            $this->row(['Email' => 'c@example.test', 'Inicio del periodo' => '01/07/2026', 'Fin del periodo' => '30/06/2027']),
        ]);

        $this->assertSame('expired', $this->membershipOf('a@example.test')->status->value);
        $this->assertSame('active', $this->membershipOf('b@example.test')->status->value);
        $this->assertSame('active', $this->membershipOf('c@example.test')->status->value);
    }

    public function test_a_period_without_end_ends_as_its_type_says(): void
    {
        $this->import([
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '15/03/2026']),
            $this->row(['Email' => 'b@example.test', 'Tipo de periodo' => 'Año académico', 'Inicio del periodo' => '01/09/2025']),
            $this->row(['Email' => 'c@example.test', 'Tipo de periodo' => 'Rotativo', 'Inicio del periodo' => '01/03/2026']),
        ]);

        $calendar = $this->membershipOf('a@example.test');
        $this->assertSame('calendar_year', $calendar->period_type->value);
        $this->assertSame('2026-03-15', $calendar->start_date->toDateString());
        $this->assertSame('2026-12-31', $calendar->end_date->toDateString());
        $this->assertSame('2026-06-30', $this->membershipOf('b@example.test')->end_date->toDateString());
        $this->assertSame('2027-02-28', $this->membershipOf('c@example.test')->end_date->toDateString());
    }

    public function test_a_period_with_only_its_type_is_the_current_one(): void
    {
        $this->import([$this->row(['Tipo de periodo' => 'Año natural'])]);

        $membership = MembershipModel::query()->sole();
        $this->assertSame('2026-01-01', $membership->start_date->toDateString());
        $this->assertSame('2026-12-31', $membership->end_date->toDateString());
        $this->assertSame('active', $membership->status->value);
    }

    public function test_a_period_with_only_its_end_starts_as_its_type_says(): void
    {
        $this->import([
            $this->row(['Email' => 'a@example.test', 'Fin del periodo' => '31/12/2025']),
            $this->row(['Email' => 'b@example.test', 'Tipo de periodo' => 'Rotativo', 'Fin del periodo' => '28/02/2027']),
        ]);

        $this->assertSame('2025-01-01', $this->membershipOf('a@example.test')->start_date->toDateString());
        $this->assertSame('2026-03-01', $this->membershipOf('b@example.test')->start_date->toDateString());
    }

    public function test_the_default_period_type_comes_from_the_module_settings(): void
    {
        config()->set('modules.settings.memberships.default_period_type', 'rolling');

        $this->import([$this->row(['Inicio del periodo' => '01/03/2026'])]);

        $this->assertSame('rolling', MembershipModel::query()->sole()->period_type->value);
    }

    public function test_a_rolling_period_ending_on_29_february_starts_on_1_march(): void
    {
        $this->import([$this->row(['Tipo de periodo' => 'Rotativo', 'Fin del periodo' => '29/02/2028'])]);

        $this->assertSame('2027-03-01', MembershipModel::query()->sole()->start_date->toDateString());
    }

    public function test_dates_without_a_type_give_the_type_they_fit(): void
    {
        $this->import([
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/09/2025', 'Fin del periodo' => '30/06/2026']),
            $this->row(['Email' => 'b@example.test', 'Inicio del periodo' => '01/01/2026', 'Fin del periodo' => '31/12/2026']),
            $this->row(['Email' => 'c@example.test', 'Inicio del periodo' => '15/03/2026', 'Fin del periodo' => '14/03/2027']),
        ]);

        $this->assertSame('academic_year', $this->membershipOf('a@example.test')->period_type->value);
        // A calendar year is a rolling year too: the default type wins
        $this->assertSame('calendar_year', $this->membershipOf('b@example.test')->period_type->value);
        $this->assertSame('rolling', $this->membershipOf('c@example.test')->period_type->value);
    }

    public function test_when_several_types_fit_the_default_one_wins(): void
    {
        config()->set('modules.settings.memberships.default_period_type', 'rolling');

        $this->import([$this->row(['Inicio del periodo' => '01/01/2026', 'Fin del periodo' => '31/12/2026'])]);

        $this->assertSame('rolling', MembershipModel::query()->sole()->period_type->value);
    }

    public function test_dates_that_fit_no_type_need_the_type(): void
    {
        $row = $this->onlyRow($this->preview([$this->row(['Inicio del periodo' => '01/02/2026', 'Fin del periodo' => '15/06/2026'])]));

        $this->assertSame(MemberImportRowStatus::Invalid, $row->status);
        $this->assertArrayHasKey(MemberImportColumn::PeriodType->value, $row->errors);
    }

    public function test_the_days_given_are_kept_as_they_are(): void
    {
        $this->import([$this->row(['Tipo de periodo' => 'Año natural', 'Inicio del periodo' => '15/03/2026', 'Fin del periodo' => '30/11/2026'])]);

        $membership = MembershipModel::query()->sole();
        $this->assertSame('2026-03-15', $membership->start_date->toDateString());
        $this->assertSame('2026-11-30', $membership->end_date->toDateString());
    }

    // Fees

    public function test_the_fee_takes_the_currency_of_the_default_fee_structure(): void
    {
        FeeStructureModel::create([
            'id' => (string) Str::uuid(),
            'member_type' => 'student',
            'period_type' => 'calendar_year',
            'amount' => 15,
            'currency' => 'usd',
            'valid_from' => '2026-01-01',
            'is_default' => true,
        ]);

        $this->import([
            $this->row(['Email' => 'a@example.test', 'Tipo de socio' => 'Estudiante', 'Tipo de periodo' => 'Año natural', 'Importe' => '15']),
            $this->row(['Email' => 'b@example.test', 'Tipo de periodo' => 'Año natural', 'Importe' => '25']),
        ]);

        $this->assertSame('USD', $this->feeOf('a@example.test')->currency);
        $this->assertSame('EUR', $this->feeOf('b@example.test')->currency);
    }

    // All or nothing

    public function test_an_invalid_row_stops_the_whole_import(): void
    {
        $result = $this->import([
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/01/2026', 'Importe' => '25']),
            $this->row(['Email' => 'b@example.test', 'Fecha de alta' => 'ayer']),
        ]);

        $this->assertFalse($result->imported);
        $this->assertSame(0, $result->createdCount);
        $this->assertSame(1, $result->report->invalidCount());
        $this->assertSame(MemberImportRowStatus::New, $result->report->rows[0]->status);
        $this->assertSame(0, MemberModel::query()->count());
        $this->assertSame(0, MembershipModel::query()->count());
    }

    public function test_a_failure_while_creating_leaves_nothing_behind(): void
    {
        $saved = 0;
        MembershipFeeModel::creating(static function () use (&$saved): void {
            if (++$saved === 2) {
                throw new RuntimeException('Storage failed');
            }
        });

        try {
            $this->import([
                $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/01/2026', 'Importe' => '25']),
                $this->row(['Email' => 'b@example.test', 'Inicio del periodo' => '01/01/2026', 'Importe' => '25']),
            ]);
            $this->fail('The failure was swallowed');
        } catch (RuntimeException $exception) {
            $this->assertSame('Storage failed', $exception->getMessage());
        }

        $this->assertSame(0, MemberModel::query()->count());
        $this->assertSame(0, MembershipModel::query()->count());
        $this->assertSame(0, MembershipFeeModel::query()->count());
    }

    public function test_a_database_failure_reaches_nobody_with_the_rows_data(): void
    {
        // Another write takes the planned number between the check and the insert
        MemberModel::creating(static function (MemberModel $member): void {
            DB::table('memberships_members')->insert([
                'id' => (string) Str::uuid(),
                'member_number' => $member->member_number,
                'first_name' => 'Otra',
                'last_name' => 'Persona',
                'member_type' => 'regular',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        });

        try {
            $this->import([$this->row(['Email' => 'ana@example.test', 'Fecha de alta' => '10/02/2024'])]);
            $this->fail('The failure was swallowed');
        } catch (MemberImportFailedException $exception) {
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('ana@example.test', $exception->getMessage());
            $this->assertStringNotContainsString('Pérez', $exception->getMessage());
            $this->assertStringNotContainsString('2024-0001', $exception->getMessage());
        }

        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_only_one_import_runs_at_a_time(): void
    {
        $running = Cache::lock(MemberImportService::LOCK, 60);
        $this->assertTrue($running->get());

        $busy = $this->import([$this->row()]);

        $this->assertTrue($busy->busy);
        $this->assertFalse($busy->imported);
        $this->assertNotNull($busy->message());
        $this->assertSame(0, MemberModel::query()->count());

        $running->release();
        $this->assertTrue($this->import([$this->row()])->imported);
        $this->assertTrue(Cache::lock(MemberImportService::LOCK, 60)->get());
    }

    public function test_more_than_a_thousand_rows_are_refused(): void
    {
        $rows = [];
        for ($i = 0; $i <= MemberImportServiceInterface::MAX_ROWS; $i++) {
            $rows[] = $this->row();
        }

        $result = $this->import($rows);

        $this->assertTrue($result->report->rowLimitExceeded);
        $this->assertFalse($result->report->canImport());
        $this->assertSame([], $result->report->rows);
        $this->assertFalse($result->imported);
        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_a_thousand_rows_are_accepted(): void
    {
        $rows = [];
        for ($i = 0; $i < MemberImportServiceInterface::MAX_ROWS; $i++) {
            $rows[] = $this->row(['Fecha de alta' => '01/01/2026']);
        }

        $result = $this->import($rows);

        $this->assertTrue($result->imported);
        $this->assertSame(MemberImportServiceInterface::MAX_ROWS, MemberModel::query()->count());
        $this->assertSame('2026-1000', MemberModel::query()->max('member_number'));
    }

    // Linking accounts

    public function test_accounts_are_not_linked_unless_asked(): void
    {
        $this->account('ana@example.test');

        $result = $this->import([$this->row(['Email' => 'ana@example.test'])]);

        $this->assertNull($result->report->rows[0]->linkedUserId);
        $this->assertNull(MemberModel::query()->sole()->user_id);
    }

    public function test_the_only_active_verified_account_with_the_email_is_linked(): void
    {
        $account = $this->account('Ana@Example.test', ['display_name' => 'Ana P.']);

        $preview = $this->preview([$this->row(['Email' => 'ana@example.test'])], linkByEmail: true);
        $this->assertSame($account->id, $preview->rows[0]->linkedUserId);
        $this->assertSame('Ana P.', $preview->rows[0]->linkedUserName);
        $this->assertSame(1, $preview->linkedCount());

        $this->import([$this->row(['Email' => 'ana@example.test'])], linkByEmail: true);
        $this->assertSame($account->id, MemberModel::query()->sole()->user_id);
    }

    public function test_an_unverified_account_is_not_linked(): void
    {
        $this->account('ana@example.test', ['email_verified_at' => null]);

        $this->assertNotLinked();
    }

    public function test_a_deactivated_account_is_not_linked(): void
    {
        $this->account('ana@example.test')->delete();

        $this->assertNotLinked();
    }

    public function test_an_anonymized_account_is_not_linked(): void
    {
        $this->account('ana@example.test', ['anonymized_at' => now()]);

        $this->assertNotLinked();
    }

    public function test_several_accounts_with_the_email_are_not_linked(): void
    {
        $this->account('ana@example.test');
        // The core keeps emails in lower case now; older accounts may still differ in case
        $older = $this->account('otra@example.test');
        DB::table('users')->where('id', $older->id)->update(['email' => 'ANA@example.test', 'deleted_at' => now()]);

        $this->assertNotLinked();
    }

    public function test_an_account_already_linked_to_a_member_is_not_linked_again(): void
    {
        $account = $this->account('ana@example.test');
        $this->existingMember(['email' => 'otra@example.test', 'user_id' => $account->id]);

        $this->assertNotLinked();
    }

    public function test_an_account_linked_to_a_deleted_member_is_not_linked_again(): void
    {
        $account = $this->account('ana@example.test');
        $this->existingMember(['email' => 'otra@example.test', 'user_id' => $account->id])->delete();

        $this->assertNotLinked();
    }

    public function test_linking_an_account_that_already_holds_the_role_changes_no_role_and_sends_nothing(): void
    {
        $account = $this->associationRoleHolder('ana@example.test');
        $auditRows = DB::table('role_audit_log')->count();
        Notification::fake();
        Mail::fake();

        $result = $this->import([$this->row(['Email' => 'ana@example.test', 'Tipo de periodo' => 'Año natural'])], linkByEmail: true);

        $this->assertSame($account->id, MemberModel::query()->sole()->user_id);
        $this->assertSame(1, $result->report->linkedCount());
        $this->assertSame($auditRows, DB::table('role_audit_log')->count());
        $this->assertTrue($this->app->make(AuthorizationServiceInterface::class)->hasRole($account, 'socio'));
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_an_account_is_not_linked_when_it_would_lose_the_association_role(): void
    {
        $this->associationRoleHolder('a@example.test');
        $this->associationRoleHolder('b@example.test');
        $permanent = $this->associationRoleHolder('c@example.test');
        $auditRows = DB::table('role_audit_log')->count();
        Notification::fake();

        $result = $this->import([
            $this->row(['Email' => 'a@example.test']),
            $this->row(['Email' => 'b@example.test', 'Inicio del periodo' => '01/01/2025', 'Fin del periodo' => '31/12/2025']),
            $this->row(['Email' => 'c@example.test', 'Tipo de socio' => 'Honorario']),
        ], linkByEmail: true);

        foreach ([0, 1] as $index) {
            $row = $result->report->rows[$index];
            $this->assertSame(MemberImportRowStatus::New, $row->status);
            $this->assertNull($row->linkedUserId);
            $this->assertSame(MemberImportLinkSkipReason::WouldRemoveAssociationRole, $row->linkSkipReason);
            $this->assertNotSame('', (string) $row->toArray()['linkSkipMessage']);
        }

        $this->assertNull(MemberModel::query()->where('email', 'a@example.test')->sole()->user_id);
        $this->assertNull(MemberModel::query()->where('email', 'b@example.test')->sole()->user_id);
        $this->assertSame($permanent->id, MemberModel::query()->where('email', 'c@example.test')->sole()->user_id);
        $this->assertSame($auditRows, DB::table('role_audit_log')->count());
        Notification::assertNothingSent();
    }

    public function test_without_role_assignment_an_account_holding_the_role_is_linked(): void
    {
        $account = $this->associationRoleHolder('ana@example.test');
        config()->set('modules.settings.memberships.enable_role_assignment', false);

        $result = $this->import([$this->row(['Email' => 'ana@example.test'])], linkByEmail: true);

        $this->assertNull($result->report->rows[0]->linkSkipReason);
        $this->assertSame($account->id, MemberModel::query()->sole()->user_id);
    }

    public function test_linking_gives_the_association_role_through_the_synchroniser(): void
    {
        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        config()->set('modules.settings.memberships.enable_role_assignment', true);

        $active = $this->account('a@example.test');
        $expired = $this->account('b@example.test');

        $this->import([
            $this->row(['Email' => 'a@example.test', 'Inicio del periodo' => '01/01/2026']),
            $this->row(['Email' => 'b@example.test', 'Inicio del periodo' => '01/01/2025', 'Fin del periodo' => '31/12/2025']),
        ], linkByEmail: true);

        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $this->assertTrue($authorization->hasRole($active, 'socio'));
        $this->assertFalse($authorization->hasRole($expired, 'socio'));
    }

    // Side effects

    public function test_importing_sends_no_mail_or_notice(): void
    {
        Notification::fake();
        Mail::fake();
        config()->set('modules.settings.memberships.send_expired_email', true);
        config()->set('modules.settings.memberships.send_overdue_email', true);
        $this->account('ana@example.test');

        $this->import([$this->row([
            'Email' => 'ana@example.test',
            'Inicio del periodo' => '01/01/2025',
            'Fin del periodo' => '31/12/2025',
            'Importe' => '25',
            'Vencimiento' => '31/01/2025',
        ])], linkByEmail: true);

        $this->assertSame(1, MemberModel::query()->count());
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_personal_data_never_reaches_the_log(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, static function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message->message.' '.json_encode($message->context);
        });
        $this->existingMember(['email' => 'luis@example.test']);

        $this->import([
            $this->row(['Email' => 'ana@example.test', 'Teléfono' => '600123456']),
            $this->row(['Nombre' => 'Luis', 'Email' => 'luis@example.test']),
        ]);
        $this->import([$this->row(['Email' => 'ana@example', 'Apellidos' => 'Zubizarreta'])]);

        $this->assertNotSame([], $logged);

        foreach ($logged as $line) {
            foreach (['ana@example', 'luis@example.test', '600123456', 'Pérez', 'Zubizarreta'] as $personal) {
                $this->assertStringNotContainsString($personal, $line);
            }
        }
    }

    public function test_the_counts_add_up(): void
    {
        $this->existingMember(['email' => 'dup@example.test']);

        $preview = $this->preview([
            $this->row(['Email' => 'a@example.test']),
            $this->row(['Email' => 'dup@example.test']),
            $this->row(['Email' => 'mal']),
        ]);

        $this->assertSame(1, $preview->newCount());
        $this->assertSame(1, $preview->duplicateCount());
        $this->assertSame(1, $preview->invalidCount());
        $this->assertSame([2, 3, 4], array_map(static fn ($row): int => $row->line, $preview->rows));
        $this->assertFalse($preview->canImport());
    }

    /**
     * An account holding the association role given by hand, and another role that needs it
     * (losing the association role would mail the holder and the managers).
     */
    private function associationRoleHolder(string $email): UserModel
    {
        RoleModel::firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        RoleModel::firstOrCreate(['name' => 'socio'], ['display_name' => 'Socio']);
        RoleModel::firstOrCreate(['name' => 'tesoreria'], ['display_name' => 'Tesorería', 'requires_association_member' => true]);
        config()->set('modules.settings.memberships.enable_role_assignment', true);

        $account = $this->account($email);
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $authorization->assignRole($account, 'socio');
        $authorization->assignRole($account, 'tesoreria');

        return $account;
    }

    private function feeOf(string $email): MembershipFeeModel
    {
        return MembershipFeeModel::query()->where('membership_id', $this->membershipOf($email)->id)->sole();
    }

    private function assertNotLinked(): void
    {
        $preview = $this->preview([$this->row(['Email' => 'ana@example.test'])], linkByEmail: true);
        $this->assertSame(MemberImportRowStatus::New, $preview->rows[0]->status);
        $this->assertNull($preview->rows[0]->linkedUserId);

        $this->import([$this->row(['Email' => 'ana@example.test'])], linkByEmail: true);
        $this->assertNull(MemberModel::query()->where('email', 'ana@example.test')->sole()->user_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function account(string $email, array $attributes = []): UserModel
    {
        return UserModel::factory()->create(array_merge(['email' => $email], $attributes));
    }

    private function numberOf(string $email): string
    {
        return MemberModel::query()->where('email', $email)->sole()->member_number;
    }

    private function membershipOf(string $email): MembershipModel
    {
        $memberId = MemberModel::query()->where('email', $email)->sole()->id;

        return MembershipModel::query()->where('member_id', $memberId)->sole();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function existingMember(array $attributes = []): MemberModel
    {
        return MemberModel::create(array_merge([
            'id' => (string) Str::uuid(),
            'member_number' => '2026-0900',
            'first_name' => 'Luis',
            'last_name' => 'García',
            'email' => null,
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => now(),
        ], $attributes));
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function preview(array $rows, bool $linkByEmail = false): MemberImportPreviewDTO
    {
        return $this->app->make(MemberImportServiceInterface::class)
            ->preview($this->rows($rows), new MemberImportOptionsDTO(linkByEmail: $linkByEmail));
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function import(array $rows, bool $linkByEmail = false): MemberImportResultDTO
    {
        return $this->app->make(MemberImportServiceInterface::class)
            ->import($this->rows($rows), new MemberImportOptionsDTO(linkByEmail: $linkByEmail));
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
