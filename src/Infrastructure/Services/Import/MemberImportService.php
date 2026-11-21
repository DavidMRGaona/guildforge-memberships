<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services\Import;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Memberships\Application\DTOs\Import\LinkableAccountDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowReportDTO;
use Modules\Memberships\Application\Services\LinkableAccountFinderInterface;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;
use Modules\Memberships\Application\Services\MemberNumberGeneratorInterface;
use Modules\Memberships\Domain\Entities\Member;
use Modules\Memberships\Domain\Entities\Membership;
use Modules\Memberships\Domain\Entities\MembershipFee;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MemberImportDuplicateReason;
use Modules\Memberships\Domain\Enums\MemberImportRowStatus;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Repositories\MemberRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipFeeRepositoryInterface;
use Modules\Memberships\Domain\Repositories\MembershipRepositoryInterface;
use Modules\Memberships\Domain\ValueObjects\MemberId;
use Modules\Memberships\Domain\ValueObjects\MemberNumber;
use Modules\Memberships\Domain\ValueObjects\MembershipFeeId;
use Modules\Memberships\Domain\ValueObjects\MembershipId;
use Modules\Memberships\Domain\ValueObjects\Money;
use Modules\Memberships\Infrastructure\Services\MembershipSettingsReader;

/**
 * Members are saved through the repositories, so the model observers keep the association
 * member role in line (MemberRoleSynchronizer) as for any other member. A period is created
 * active, or expired when it already ended: no expiry event is raised, so nothing is mailed.
 */
final readonly class MemberImportService implements MemberImportServiceInterface
{
    /** Fees are kept in euros, as the panel creates them. */
    private const string CURRENCY = 'EUR';

    public function __construct(
        private MemberRepositoryInterface $members,
        private MembershipRepositoryInterface $memberships,
        private MembershipFeeRepositoryInterface $fees,
        private MemberNumberGeneratorInterface $numbers,
        private LinkableAccountFinderInterface $accounts,
        private MembershipSettingsReader $settings,
    ) {}

    /**
     * @param  list<MemberImportRowDTO>  $rows
     */
    public function preview(array $rows, MemberImportOptionsDTO $options): MemberImportPreviewDTO
    {
        return $this->analyse($rows, $options)['report'];
    }

    /**
     * @param  list<MemberImportRowDTO>  $rows
     */
    public function import(array $rows, MemberImportOptionsDTO $options): MemberImportResultDTO
    {
        $result = DB::transaction(function () use ($rows, $options): MemberImportResultDTO {
            // Checked again inside the transaction: numbers are planned from what it sees
            ['report' => $report, 'planned' => $planned] = $this->analyse($rows, $options);

            if (! $report->canImport()) {
                return new MemberImportResultDTO(imported: false, report: $report);
            }

            foreach ($planned as $plan) {
                $this->create($plan['row'], $plan['number'], $plan['account']);
            }

            return new MemberImportResultDTO(imported: true, report: $report, createdCount: count($planned));
        });

        // Counts only: the rows hold personal data
        Log::info('Memberships: member import finished', [
            'imported' => $result->imported,
            'created' => $result->createdCount,
            'duplicates' => $result->report->duplicateCount(),
            'invalid' => $result->report->invalidCount(),
            'linked' => $result->imported ? $result->report->linkedCount() : 0,
        ]);

        return $result;
    }

    /**
     * @param  list<MemberImportRowDTO>  $rows
     * @return array{report: MemberImportPreviewDTO, planned: list<array{row: ParsedMemberImportRow, number: MemberNumber, account: LinkableAccountDTO|null}>}
     */
    private function analyse(array $rows, MemberImportOptionsDTO $options): array
    {
        if (count($rows) > self::MAX_ROWS) {
            return ['report' => new MemberImportPreviewDTO(rows: [], rowLimitExceeded: true), 'planned' => []];
        }

        $headers = MemberImportHeaders::resolve($this->headerNames($rows));
        $parser = new MemberImportRowParser($this->settings);

        /** @var list<ParsedMemberImportRow> $parsed */
        $parsed = [];
        /** @var list<array<string, list<string>>> $errors */
        $errors = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row->values as $header => $text) {
                $column = $headers->columnFor((string) $header);

                if ($column instanceof MemberImportColumn) {
                    $cells[$column->value] = (string) $text;
                }
            }

            $parsedRow = $parser->parse($cells);
            $parsed[] = $parsedRow;
            $errors[] = $parsedRow->errors;
        }

        $this->flagRepeats($rows, $parsed, $errors, MemberImportColumn::MemberNumber, static fn (ParsedMemberImportRow $row): ?string => $row->memberNumber?->value);
        $this->flagRepeats($rows, $parsed, $errors, MemberImportColumn::Email, static fn (ParsedMemberImportRow $row): ?string => $row->emailKey());

        $statuses = $this->statuses($parsed, $errors);

        // Numbers for the new rows only: the others are never created
        $reserved = [];
        foreach ($parsed as $index => $row) {
            if ($statuses[$index]['status'] === MemberImportRowStatus::New && $row->memberNumber instanceof MemberNumber) {
                $reserved[] = $row->memberNumber->value;
            }
        }

        $planner = new MemberNumberPlanner($this->numbers, $reserved);
        /** @var array<int, MemberNumber> $numbers */
        $numbers = [];

        foreach ($parsed as $index => $row) {
            if ($statuses[$index]['status'] !== MemberImportRowStatus::New) {
                continue;
            }

            $year = (int) $row->joinedAt->format('Y');
            $number = $row->memberNumber ?? $planner->next($year);

            if (! $number instanceof MemberNumber) {
                $errors[$index][MemberImportColumn::MemberNumber->value][] = (string) __('memberships::memberships.import.errors.member_numbers_exhausted', ['year' => $year]);
                $statuses[$index]['status'] = MemberImportRowStatus::Invalid;

                continue;
            }

            $numbers[$index] = $number;
        }

        $accounts = $options->linkByEmail ? $this->linkableAccounts($parsed, $statuses) : [];

        $reports = [];
        $planned = [];

        foreach ($parsed as $index => $row) {
            $status = $statuses[$index]['status'];
            $account = $status === MemberImportRowStatus::New && $row->emailKey() !== null
                ? ($accounts[$row->emailKey()] ?? null)
                : null;

            $reports[] = new MemberImportRowReportDTO(
                line: $rows[$index]->line,
                status: $status,
                duplicateReason: $statuses[$index]['reason'],
                errors: $status === MemberImportRowStatus::Invalid ? $errors[$index] : [],
                memberNumber: isset($numbers[$index]) ? $numbers[$index]->value : $row->memberNumberText,
                fullName: $row->fullName(),
                hasPeriod: $row->hasPeriod,
                hasFee: $row->hasFee,
                linkedUserId: $account?->id,
                linkedUserName: $account?->name,
            );

            if ($status === MemberImportRowStatus::New && isset($numbers[$index])) {
                $planned[] = ['row' => $row, 'number' => $numbers[$index], 'account' => $account];
            }
        }

        return [
            'report' => new MemberImportPreviewDTO(
                rows: $reports,
                unknownColumns: $headers->unknown,
                repeatedColumns: $headers->repeated,
            ),
            'planned' => $planned,
        ];
    }

    /**
     * A row is skipped when a member (deleted ones too) has its number or its email, even if
     * it has other mistakes: it would never be created anyway. Otherwise any mistake makes it
     * invalid.
     *
     * @param  list<ParsedMemberImportRow>  $parsed
     * @param  list<array<string, list<string>>>  $errors
     * @return list<array{status: MemberImportRowStatus, reason: MemberImportDuplicateReason|null}>
     */
    private function statuses(array $parsed, array $errors): array
    {
        $numbers = array_values(array_filter(array_map(
            static fn (ParsedMemberImportRow $row): ?string => $row->memberNumber?->value,
            $parsed,
        )));
        $emails = array_values(array_filter(array_map(
            static fn (ParsedMemberImportRow $row): ?string => $row->emailKey(),
            $parsed,
        )));

        $takenNumbers = array_fill_keys($numbers === [] ? [] : $this->members->existingMemberNumbers($numbers), true);
        $takenEmails = array_fill_keys($emails === [] ? [] : $this->members->existingEmails($emails), true);

        $statuses = [];

        foreach ($parsed as $index => $row) {
            $reason = match (true) {
                $row->memberNumber instanceof MemberNumber && isset($takenNumbers[$row->memberNumber->value]) => MemberImportDuplicateReason::MemberNumber,
                $row->emailKey() !== null && isset($takenEmails[$row->emailKey()]) => MemberImportDuplicateReason::Email,
                default => null,
            };

            $statuses[] = [
                'status' => match (true) {
                    $reason !== null => MemberImportRowStatus::Duplicate,
                    $errors[$index] !== [] => MemberImportRowStatus::Invalid,
                    default => MemberImportRowStatus::New,
                },
                'reason' => $reason,
            ];
        }

        return $statuses;
    }

    /**
     * Rows of the same file sharing a value of the column: each gets an error naming the
     * lines that share it.
     *
     * @param  list<MemberImportRowDTO>  $rows
     * @param  list<ParsedMemberImportRow>  $parsed
     * @param  list<array<string, list<string>>>  $errors
     * @param  callable(ParsedMemberImportRow): ?string  $key
     */
    private function flagRepeats(array $rows, array $parsed, array &$errors, MemberImportColumn $column, callable $key): void
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach ($parsed as $index => $row) {
            $value = $key($row);

            if ($value !== null) {
                $groups[$value][] = $index;
            }
        }

        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $lines = Arr::join(
                array_map(static fn (int $index): string => (string) $rows[$index]->line, $indexes),
                ', ',
                (string) __('memberships::memberships.import.last_separator'),
            );

            foreach ($indexes as $index) {
                $errors[$index][$column->value][] = (string) __('memberships::memberships.import.errors.repeated_in_file', ['lines' => $lines]);
            }
        }
    }

    /**
     * @param  list<ParsedMemberImportRow>  $parsed
     * @param  list<array{status: MemberImportRowStatus, reason: MemberImportDuplicateReason|null}>  $statuses
     * @return array<string, LinkableAccountDTO>
     */
    private function linkableAccounts(array $parsed, array $statuses): array
    {
        $emails = [];

        foreach ($parsed as $index => $row) {
            if ($statuses[$index]['status'] === MemberImportRowStatus::New && $row->emailKey() !== null) {
                $emails[] = $row->emailKey();
            }
        }

        return $emails === [] ? [] : $this->accounts->findByEmails($emails);
    }

    /**
     * @param  list<MemberImportRowDTO>  $rows
     * @return list<string>
     */
    private function headerNames(array $rows): array
    {
        $headers = [];

        foreach ($rows as $row) {
            foreach (array_keys($row->values) as $header) {
                $headers[(string) $header] = true;
            }
        }

        return array_map(strval(...), array_keys($headers));
    }

    private function create(ParsedMemberImportRow $row, MemberNumber $number, ?LinkableAccountDTO $account): void
    {
        $member = new Member(
            id: MemberId::generate(),
            memberNumber: $number,
            firstName: (string) $row->firstName,
            lastName: (string) $row->lastName,
            email: $row->email,
            phone: $row->phone,
            birthDate: $row->birthDate,
            address: $row->address,
            memberType: $row->memberType,
            status: MemberStatus::Active,
            userId: $account?->id,
            notes: $row->notes,
            joinedAt: $row->joinedAt,
        );

        $this->members->save($member);

        if (! $row->createsPeriod()) {
            return;
        }

        $now = now()->toDateTimeImmutable();
        $membership = new Membership(
            id: MembershipId::generate(),
            memberId: $member->id,
            periodType: $row->periodType,
            startDate: $row->periodStart,
            endDate: $row->periodEnd,
            status: MembershipStatus::Active,
            activatedAt: $row->periodStart < $now ? $row->periodStart : $now,
        );

        if ($membership->isExpired($now)) {
            $membership->expire();
        }

        $this->memberships->save($membership);

        if (! $row->createsFee()) {
            return;
        }

        $this->fees->save(new MembershipFee(
            id: MembershipFeeId::generate(),
            membershipId: $membership->id,
            amount: Money::fromCents($row->feeAmountCents, self::CURRENCY),
            dueDate: $row->feeDueDate ?? $row->periodStart,
            paidAt: $row->feePaidAt,
            paymentMethod: $row->paymentMethod,
            transactionReference: $row->paymentReference,
        ));
    }
}
