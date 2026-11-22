<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Actions;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Services\SpreadsheetWriterInterface;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetReadException;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetWriteException;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Impersonation\ReadOnlyActions;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MembershipPeriodType;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Domain\Enums\PaymentMethod;
use Modules\Memberships\Domain\Exceptions\MemberImportFailedException;
use Modules\Memberships\Infrastructure\Services\Import\MemberImportSpreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Importar socios» on the members list (memberships#95): upload a `.csv` or `.xlsx`, see what
 * would happen to each row, then import. The file is read with the core's spreadsheet reader
 * and handed to MemberImportServiceInterface, which checks every row again when importing, so
 * nothing shown in the preview is trusted. The uploaded file is deleted once the import ends,
 * whatever its outcome.
 *
 * Not marked as reading: a read-only impersonation refuses it. The template it offers only
 * reads, so that one is.
 */
final class ImportMembersAction
{
    use ChecksPermissions;

    public const string NAME = 'importMembers';

    public const string TEMPLATE_NAME = 'downloadMemberImportTemplate';

    public const string PERMISSION = 'memberships:members.import';

    public static function make(): Action
    {
        /** @var view-string $previewView */
        $previewView = 'memberships::filament.member-import-preview';

        return Action::make(self::NAME)
            ->label(__('memberships::memberships.import.action.label'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->authorize(static fn (): bool => self::allowed())
            ->modalHeading(__('memberships::memberships.import.action.heading'))
            ->modalDescription(__('memberships::memberships.import.action.description'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('memberships::memberships.import.action.submit'))
            // Only a file the import would accept now; the server checks it all again anyway
            ->modalSubmitAction(static fn (Action $action, Action $import): Action => $action->disabled(! self::canImport(self::mountedData($import))))
            ->steps([
                Step::make('file')
                    ->label(__('memberships::memberships.import.steps.file'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('memberships::memberships.import.fields.file'))
                            ->helperText(__('memberships::memberships.import.fields.file_help'))
                            ->required()
                            ->storeFiles(false)
                            ->maxSize(intdiv(MemberImportSpreadsheet::MAX_BYTES, 1024))
                            ->validationMessages(['max' => __('memberships::memberships.import.fields.file_too_large')])
                            ->extraInputAttributes(['accept' => '.csv,.xlsx'])
                            ->rule(static fn (): Closure => self::readableRule()),
                        Checkbox::make('link_by_email')
                            ->label(__('memberships::memberships.import.fields.link_by_email'))
                            ->helperText(__('memberships::memberships.import.fields.link_by_email_help'))
                            ->default(false),
                        Actions::make([self::templateAction()])->key('template'),
                    ]),
                Step::make('preview')
                    ->label(__('memberships::memberships.import.steps.preview'))
                    ->schema([
                        View::make($previewView)
                            ->viewData(static fn (Get $get): array => self::previewData($get('file'), (bool) $get('link_by_email'))),
                    ]),
            ])
            ->action(static function (array $data): void {
                self::import($data);
            });
    }

    /** The `.xlsx` with every column and one example row, built with the core's writer. */
    public static function templateAction(): Action
    {
        return ReadOnlyActions::allow(Action::make(self::TEMPLATE_NAME)
            ->label(__('memberships::memberships.import.template.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->link()
            ->authorize(static fn (): bool => self::allowed())
            ->action(static fn (): ?StreamedResponse => self::downloadTemplate()));
    }

    /**
     * The template as a download; nothing without the permission, and a notification instead
     * of an error when the writer fails.
     */
    public static function downloadTemplate(): ?StreamedResponse
    {
        if (! self::allowed()) {
            self::notify('danger', 'not_allowed');

            return null;
        }

        try {
            $bytes = self::templateBytes();
        } catch (SpreadsheetWriteException) {
            self::notify('danger', 'template_failed');

            return null;
        }

        return response()->streamDownload(
            static function () use ($bytes): void {
                echo $bytes;
            },
            (string) __('memberships::memberships.import.template.file_name'),
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public static function templateBytes(): string
    {
        $today = app(TimezoneServiceInterface::class)->today();
        $year = $today->format('Y');
        $example = [
            MemberImportColumn::FirstName->value => 'Ana',
            MemberImportColumn::LastName->value => 'Pérez Gómez',
            MemberImportColumn::Email->value => 'ana@example.org',
            MemberImportColumn::Phone->value => '600 000 000',
            MemberImportColumn::BirthDate->value => '17/05/1990',
            MemberImportColumn::Address->value => (string) __('memberships::memberships.import.template.example_address'),
            MemberImportColumn::MemberType->value => MemberType::Regular->label(),
            MemberImportColumn::JoinedAt->value => '01/01/'.$year,
            MemberImportColumn::MemberNumber->value => null,
            MemberImportColumn::Notes->value => null,
            MemberImportColumn::PeriodType->value => MembershipPeriodType::CalendarYear->label(),
            MemberImportColumn::PeriodStart->value => '01/01/'.$year,
            MemberImportColumn::PeriodEnd->value => '31/12/'.$year,
            MemberImportColumn::FeeAmount->value => '30,00',
            MemberImportColumn::FeeDueDate->value => '31/01/'.$year,
            MemberImportColumn::FeePaidAt->value => $today->format('d/m/Y'),
            MemberImportColumn::PaymentMethod->value => PaymentMethod::BankTransfer->label(),
            MemberImportColumn::PaymentReference->value => (string) __('memberships::memberships.import.template.example_reference'),
        ];

        return app(SpreadsheetWriterInterface::class)->write(new SpreadsheetTemplateDTO(
            headers: array_map(static fn (MemberImportColumn $column): string => $column->label(), MemberImportColumn::cases()),
            rows: [array_map(static fn (MemberImportColumn $column): ?string => $example[$column->value], MemberImportColumn::cases())],
            sheetName: (string) __('memberships::memberships.import.template.sheet_name'),
        ));
    }

    /**
     * The reader's own message on the file field when the file is not a spreadsheet it reads.
     */
    private static function readableRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $file = self::uploadedFile($value);

            // Nothing reaches the reader without the permission, whatever the page was sent
            if (! self::allowed() || ! $file instanceof TemporaryUploadedFile) {
                return;
            }

            try {
                app(MemberImportSpreadsheet::class)->rows($file->getRealPath());
            } catch (SpreadsheetReadException $exception) {
                $fail($exception->userMessage());
            }
        };
    }

    /**
     * @return array{preview: MemberImportPreviewDTO|null, error: string|null}
     */
    private static function previewData(mixed $state, bool $linkByEmail): array
    {
        $file = self::uploadedFile($state);

        // Filament draws a mounted action's modal without asking whether the action may be
        // seen, and the page's mounted actions can be written by hand: a preview reveals
        // which emails have members or accounts, so it needs the permission itself
        if (! self::allowed() || ! $file instanceof TemporaryUploadedFile) {
            return ['preview' => null, 'error' => null];
        }

        try {
            return ['preview' => app(MemberImportSpreadsheet::class)->preview($file->getRealPath(), $linkByEmail), 'error' => null];
        } catch (SpreadsheetReadException $exception) {
            return ['preview' => null, 'error' => $exception->userMessage()];
        }
    }

    /**
     * What the dialog holds now, as the page keeps it: the action's own data is not filled in
     * yet when its buttons are drawn.
     *
     * @return array<string, mixed>
     */
    private static function mountedData(Action $import): array
    {
        $livewire = $import->getLivewire();
        $mounted = is_object($livewire) && property_exists($livewire, 'mountedActions') ? $livewire->mountedActions : [];

        foreach (array_reverse(is_array($mounted) ? $mounted : []) as $entry) {
            if (is_array($entry) && ($entry['name'] ?? null) === self::NAME && is_array($entry['data'] ?? null)) {
                return $entry['data'];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function canImport(array $data): bool
    {
        $preview = self::previewData($data['file'] ?? null, (bool) ($data['link_by_email'] ?? false))['preview'];

        return $preview instanceof MemberImportPreviewDTO && $preview->canImport();
    }

    /**
     * Reads the file again and imports it; the file is forgotten whatever happens.
     *
     * @param  array<string, mixed>  $data
     */
    public static function import(array $data): void
    {
        $file = self::uploadedFile($data['file'] ?? null);

        try {
            // Checked again here: the permission may be gone since the dialog opened
            if (! self::allowed()) {
                self::notify('danger', 'not_allowed');

                return;
            }

            // The field is required: only a forged call gets here without a file
            if (! $file instanceof TemporaryUploadedFile) {
                return;
            }

            try {
                $rows = app(MemberImportSpreadsheet::class)->rows($file->getRealPath());
            } catch (SpreadsheetReadException $exception) {
                Notification::make()
                    ->danger()
                    ->title(__('memberships::memberships.import.notifications.unreadable_title'))
                    ->body($exception->userMessage())
                    ->send();

                return;
            }

            try {
                $result = app(MemberImportServiceInterface::class)->import($rows, new MemberImportOptionsDTO(
                    linkByEmail: (bool) ($data['link_by_email'] ?? false),
                ));
            } catch (MemberImportFailedException) {
                // The service already logged the SQLSTATE; the exception carries no row data
                self::notify('danger', 'failed');

                return;
            }

            self::report($result);
        } finally {
            if ($file instanceof TemporaryUploadedFile) {
                self::forget($file);
            }
        }
    }

    /**
     * Confirming checked the dialog and refused it (a file the reader refuses, too large…):
     * the upload is forgotten and the field emptied, so a file with personal data is not left
     * behind waiting for another try. Moving between steps keeps it.
     */
    public static function forgetAfterFailedConfirmation(Component $page): void
    {
        if (! property_exists($page, 'mountedActions') || ! is_array($page->mountedActions)) {
            return;
        }

        $index = array_key_last($page->mountedActions);
        $entry = $index === null ? null : $page->mountedActions[$index];

        if (! is_array($entry) || ($entry['name'] ?? null) !== self::NAME || ! is_array($entry['data'] ?? null)) {
            return;
        }

        foreach (is_array($entry['data']['file'] ?? null) ? $entry['data']['file'] : [$entry['data']['file'] ?? null] as $state) {
            $file = self::uploadedFile($state);

            if ($file instanceof TemporaryUploadedFile) {
                self::forget($file);
            }
        }

        $page->mountedActions[$index]['data']['file'] = [];
    }

    /** The upload and the file Livewire keeps beside it with its original name. */
    private static function forget(TemporaryUploadedFile $file): void
    {
        $file->delete();
        FileUploadConfiguration::storage()->delete(FileUploadConfiguration::path($file->getFilename().'.json'));
    }

    private static function report(MemberImportResultDTO $result): void
    {
        if ($result->busy) {
            self::notify('warning', 'busy');

            return;
        }

        $report = $result->report;

        // Counts only: the rows hold personal data
        Log::info('Memberships: members imported from the panel', [
            'actor_id' => auth()->id(),
            ...self::impersonation(),
            'imported' => $result->imported,
            'created' => $result->createdCount,
            'duplicates' => $report->duplicateCount(),
            'invalid' => $report->invalidCount(),
            'linked' => $result->imported ? $report->linkedCount() : 0,
        ]);

        if (! $result->imported) {
            Notification::make()
                ->warning()
                ->title(__('memberships::memberships.import.notifications.not_imported_title'))
                ->body(self::notImportedReason($report))
                ->persistent()
                ->send();

            return;
        }

        $lines = [];

        if ($report->duplicateCount() > 0) {
            $lines[] = trans_choice('memberships::memberships.import.notifications.skipped', $report->duplicateCount(), ['count' => $report->duplicateCount()]);
        }

        if ($report->linkedCount() > 0) {
            $lines[] = trans_choice('memberships::memberships.import.notifications.linked', $report->linkedCount(), ['count' => $report->linkedCount()]);
        }

        Notification::make()
            ->success()
            ->title(trans_choice('memberships::memberships.import.notifications.imported_title', $result->createdCount, ['count' => $result->createdCount]))
            ->body($lines === [] ? null : implode(' ', $lines))
            ->send();
    }

    /**
     * Who really ran it while impersonating: a simulated import is undone, so the log must
     * not read as if the impersonated account had imported anything.
     *
     * @return array{impersonator_id?: mixed, simulated?: true}
     */
    private static function impersonation(): array
    {
        $context = app(ImpersonationContextInterface::class);

        if (! $context->isActive()) {
            return [];
        }

        return ['impersonator_id' => $context->impersonator()?->getAuthIdentifier(), 'simulated' => true];
    }

    private static function allowed(): bool
    {
        return self::userCan(self::PERMISSION);
    }

    private static function notImportedReason(MemberImportPreviewDTO $report): string
    {
        return match (true) {
            $report->rowLimitExceeded => (string) __('memberships::memberships.import.preview.too_many_rows', ['max' => MemberImportServiceInterface::MAX_ROWS]),
            $report->repeatedColumns !== [] => (string) __('memberships::memberships.import.preview.repeated_columns', ['columns' => implode(', ', $report->repeatedColumns)]),
            $report->invalidCount() > 0 => (string) __('memberships::memberships.import.preview.blocked_by_errors'),
            default => (string) __('memberships::memberships.import.preview.nothing_new'),
        };
    }

    private static function notify(string $status, string $key): void
    {
        Notification::make()
            ->status($status)
            ->title(__('memberships::memberships.import.notifications.'.$key.'_title'))
            ->body(__('memberships::memberships.import.notifications.'.$key.'_body'))
            ->send();
    }

    /** The file of the upload field: Filament keeps a list of one, Livewire's rule the file. */
    private static function uploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if (is_array($state)) {
            $state = array_first($state);
        }

        return $state instanceof TemporaryUploadedFile ? $state : null;
    }
}
