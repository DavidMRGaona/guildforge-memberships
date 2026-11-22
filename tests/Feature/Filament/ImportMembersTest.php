<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Filament;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Spreadsheets\DTOs\SpreadsheetLimitsDTO;
use App\Application\Spreadsheets\DTOs\SpreadsheetTemplateDTO;
use App\Application\Spreadsheets\Services\SpreadsheetReaderInterface;
use App\Application\Spreadsheets\Services\SpreadsheetWriterInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Spreadsheets\Exceptions\SpreadsheetWriteException;
use App\Filament\Impersonation\ReadOnlyActions;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Modules\Memberships\Application\DTOs\Import\MemberImportOptionsDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportPreviewDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportResultDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowDTO;
use Modules\Memberships\Application\DTOs\Import\MemberImportRowReportDTO;
use Modules\Memberships\Application\Services\MemberImportServiceInterface;
use Modules\Memberships\Domain\Enums\MemberImportColumn;
use Modules\Memberships\Domain\Enums\MemberImportDuplicateReason;
use Modules\Memberships\Domain\Enums\MemberImportLinkSkipReason;
use Modules\Memberships\Domain\Enums\MemberImportRowStatus;
use Modules\Memberships\Domain\Exceptions\MemberImportFailedException;
use Modules\Memberships\Filament\Actions\ImportMembersAction;
use Modules\Memberships\Filament\Resources\FeeStructureResource;
use Modules\Memberships\Filament\Resources\MemberResource;
use Modules\Memberships\Filament\Resources\MemberResource\Pages\ListMembers;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\MembershipsServiceProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * «Importar socios» on the members list: a file is read with the core's spreadsheet reader,
 * previewed and imported through the module's import service (memberships#95).
 */
final class ImportMembersTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    private const string IMPORT_PERMISSION = 'memberships:members.import';

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    /** @var array{previews: list<int>, imports: list<int>} */
    private array $serviceCalls = ['previews' => [], 'imports' => []];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([MemberResource::class, FeeStructureResource::class]);
    }

    public function test_the_import_action_sits_before_the_contextual_help(): void
    {
        $this->actingAsPanelAdmin();

        $page = Livewire::test(ListMembers::class)->instance();
        $this->assertInstanceOf(ListMembers::class, $page);
        $names = array_map(
            static fn (Action|ActionGroup $action): string => $action instanceof Action ? (string) $action->getName() : 'group',
            $page->getCachedHeaderActions(),
        );

        $this->assertSame(ImportMembersAction::NAME, $names[count($names) - 2]);
        $this->assertSame('contextualHelp', $names[count($names) - 1]);
    }

    public function test_the_action_shows_only_with_the_import_permission(): void
    {
        $this->actingAs($this->editorWith(['memberships:members.view_any']));
        Livewire::test(ListMembers::class)->assertActionHidden(ImportMembersAction::NAME);

        $this->actingAs($this->editorWith(['memberships:members.view_any', self::IMPORT_PERMISSION]));
        Livewire::test(ListMembers::class)->assertActionVisible(ImportMembersAction::NAME);
    }

    public function test_a_forged_import_without_the_permission_creates_nothing(): void
    {
        $this->actingAs($this->editorWith(['memberships:members.view_any']));

        // Filament refuses to mount a hidden action, so the mounted state is written by hand
        Livewire::test(ListMembers::class)
            ->set('mountedActions', [['name' => ImportMembersAction::NAME, 'arguments' => [], 'context' => [], 'data' => ['link_by_email' => false]]])
            ->set('mountedActions.0.data.file', $this->csv([['Ana', 'Pérez', 'ana@example.test']]))
            ->call('callMountedAction');

        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_a_dialog_forged_without_the_permission_previews_nothing(): void
    {
        UserModel::factory()->create(['email' => 'ana@example.test', 'display_name' => 'Cuenta de Ana', 'name' => 'cuenta-de-ana']);
        $this->spyImportService();
        $this->actingAs($this->editorWith(['memberships:members.view_any']));

        // Filament draws a mounted action's modal without asking whether it may be seen
        $page = Livewire::test(ListMembers::class)
            ->set('mountedActions', [['name' => ImportMembersAction::NAME, 'arguments' => [], 'context' => [], 'data' => ['link_by_email' => true]]])
            ->set('mountedActions.0.data.file', $this->csv([['Ana', 'Pérez', 'ana@example.test']]));
        $html = $this->dialogHtml($page);

        $this->assertStringNotContainsString('Cuenta de Ana', $html);
        $this->assertStringNotContainsString(e(trans_choice('memberships::memberships.import.preview.new', 1, ['count' => 1])), $html);
        $this->assertTrue($this->submitDisabled($page));
        $this->assertSame(['previews' => [], 'imports' => []], $this->serviceCalls);
    }

    public function test_calling_the_import_without_the_permission_creates_nothing_and_forgets_the_file(): void
    {
        $this->actingAs($this->editorWith(['memberships:members.view_any']));

        ImportMembersAction::import(['file' => [$this->temporaryUpload([['Ana', 'Pérez', 'ana@example.test']])], 'link_by_email' => false]);

        $this->assertSame(0, MemberModel::query()->count());
        $this->assertSame([], $this->temporaryUploads());
    }

    public function test_calling_the_import_without_a_file_says_nothing(): void
    {
        $this->actingAsPanelAdmin();

        ImportMembersAction::import(['link_by_email' => false]);

        $this->assertSame([], session('filament.notifications', []));
    }

    public function test_a_permission_lost_after_opening_the_dialog_creates_nothing(): void
    {
        $user = $this->editorWith(['memberships:members.view_any', self::IMPORT_PERMISSION]);
        $this->actingAs($user);

        $page = Livewire::test(ListMembers::class)
            ->mountAction(ImportMembersAction::NAME)
            ->setActionData(['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])]);

        $this->actingAs($this->editorWith(['memberships:members.view_any']));
        $page->callMountedAction();

        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_importing_a_file_creates_its_members_and_says_how_many(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([
                ['Ana', 'Pérez', 'ana@example.test'],
                ['Luis', 'García', 'luis@example.test'],
            ])])
            ->assertHasNoActionErrors()
            ->assertNotified(trans_choice('memberships::memberships.import.notifications.imported_title', 2, ['count' => 2]));

        $this->assertSame(['ana@example.test', 'luis@example.test'], MemberModel::query()->orderBy('email')->pluck('email')->all());
    }

    public function test_an_xlsx_file_is_imported_too(): void
    {
        $this->actingAsPanelAdmin();
        $bytes = app(SpreadsheetWriterInterface::class)->write(
            new SpreadsheetTemplateDTO(
                headers: ['Nombre', 'Apellidos', 'Email'],
                rows: [['Ana', 'Pérez', 'ana@example.test']],
            ),
        );

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => UploadedFile::fake()->createWithContent('socios.xlsx', $bytes)])
            ->assertHasNoActionErrors();

        $this->assertSame(1, MemberModel::query()->count());
    }

    public function test_duplicates_are_skipped_and_counted_in_the_notification(): void
    {
        $this->actingAsPanelAdmin();
        $this->member('2025-0001', 'luis@example.test');

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([
                ['Ana', 'Pérez', 'ana@example.test'],
                ['Luis', 'García', 'LUIS@example.test'],
            ])])
            ->assertNotified(trans_choice('memberships::memberships.import.notifications.imported_title', 1, ['count' => 1]));

        $this->assertSame(2, MemberModel::query()->count());
        $this->assertSame('Pérez', MemberModel::query()->where('email', 'ana@example.test')->sole()->last_name);
    }

    public function test_a_file_with_an_invalid_row_imports_nothing(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([
                ['Ana', 'Pérez', 'ana@example.test'],
                ['Luis', 'García', 'not-an-email'],
            ])])
            ->assertNotified(__('memberships::memberships.import.notifications.not_imported_title'));

        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_a_file_the_reader_refuses_gives_an_error_on_the_file_field(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: [
                'file' => UploadedFile::fake()->createWithContent('socios.csv', "Nombre;Apellidos\n"),
            ])
            ->assertHasActionErrors(['file']);

        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_the_file_is_required(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => null])
            ->assertHasActionErrors(['file' => 'required']);
    }

    public function test_accounts_are_linked_only_when_the_box_is_ticked(): void
    {
        $this->actingAsPanelAdmin();
        $ana = UserModel::factory()->create(['email' => 'ana@example.test']);
        $luis = UserModel::factory()->create(['email' => 'luis@example.test']);

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])]);
        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: [
                'file' => $this->csv([['Luis', 'García', 'luis@example.test']]),
                'link_by_email' => true,
            ]);

        $this->assertNull(MemberModel::query()->where('email', 'ana@example.test')->sole()->user_id);
        $this->assertSame($luis->id, MemberModel::query()->where('email', 'luis@example.test')->sole()->user_id);
        $this->assertNotSame($ana->id, $luis->id);
    }

    public function test_the_dialog_previews_the_uploaded_file(): void
    {
        $this->actingAsPanelAdmin();
        $this->member('2025-0001', 'luis@example.test');

        $page = Livewire::test(ListMembers::class)
            ->mountAction(ImportMembersAction::NAME)
            ->setActionData(['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test'], ['Luis', 'García', 'luis@example.test']])]);

        // Filament draws action modals outside the component's HTML: render the dialog itself
        $html = $this->dialogHtml($page);
        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.new', 1, ['count' => 1])), $html);
        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.duplicate', 1, ['count' => 1])), $html);
        $this->assertStringContainsString(e(__('memberships::memberships.import.preview.duplicate_reason.email')), $html);

        $this->assertSame(1, MemberModel::query()->count());
    }

    public function test_the_import_button_follows_whether_the_file_can_be_imported(): void
    {
        $this->actingAsPanelAdmin();
        $this->member('2025-0001', 'luis@example.test');

        $this->assertTrue($this->submitDisabled(Livewire::test(ListMembers::class)->mountAction(ImportMembersAction::NAME)));
        $this->assertFalse($this->submitDisabledFor([['Ana', 'Pérez', 'ana@example.test']]));
        $this->assertTrue($this->submitDisabledFor([['Ana', 'Pérez', 'ana@example.test'], ['Eva', 'Ruiz', 'not-an-email']]));
        // Only rows that already exist: nothing to create
        $this->assertTrue($this->submitDisabledFor([['Luis', 'García', 'luis@example.test']]));
    }

    public function test_the_link_box_starts_unticked(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->mountAction(ImportMembersAction::NAME)
            ->assertActionDataSet(['link_by_email' => false]);
    }

    public function test_a_busy_import_is_a_notification_not_an_error(): void
    {
        $this->actingAsPanelAdmin();
        $this->fakeImport(static fn (): MemberImportResultDTO => MemberImportResultDTO::busy());

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])])
            ->assertNotified(__('memberships::memberships.import.notifications.busy_title'));
    }

    public function test_a_database_failure_is_a_notification_not_an_error(): void
    {
        $this->actingAsPanelAdmin();
        $this->fakeImport(static fn (): never => throw new MemberImportFailedException('23505'));

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])])
            ->assertNotified(__('memberships::memberships.import.notifications.failed_title'));
    }

    public function test_the_uploaded_file_is_deleted_after_importing(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])]);

        $this->assertSame([], $this->temporaryUploads());
    }

    public function test_the_uploaded_file_is_deleted_when_nothing_is_imported(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'not-an-email']])]);

        $this->assertSame([], $this->temporaryUploads());
    }

    public function test_a_file_too_large_is_refused_in_megabytes_and_forgotten_when_confirming(): void
    {
        $this->actingAsPanelAdmin();

        $page = Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => UploadedFile::fake()->create('socios.csv', 3 * 1024, 'text/csv')])
            ->assertHasActionErrors(['file']);

        $this->assertContains(__('memberships::memberships.import.fields.file_too_large'), $page->errors()->all());
        $this->assertSame([], $this->temporaryUploads());
        $this->assertSame(0, MemberModel::query()->count());
    }

    public function test_a_file_the_reader_refuses_is_forgotten_when_confirming(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: [
                'file' => UploadedFile::fake()->createWithContent('socios.csv', "Nombre;Apellidos\n"),
            ])
            ->assertHasActionErrors(['file'])
            ->assertSet('mountedActions.0.data.file', []);

        $this->assertSame([], $this->temporaryUploads());
    }

    public function test_the_uploaded_file_is_deleted_when_the_import_fails(): void
    {
        $this->actingAsPanelAdmin();
        $this->fakeImport(static fn (): never => throw new MemberImportFailedException('08006'));

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])]);

        $this->assertSame([], $this->temporaryUploads());
    }

    public function test_the_import_reads_the_file_again_and_never_trusts_the_preview(): void
    {
        $this->actingAsPanelAdmin();
        $page = Livewire::test(ListMembers::class)
            ->mountAction(ImportMembersAction::NAME)
            ->setActionData(['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test']])]);

        // Somebody adds the member while the dialog is open
        $this->member('2025-0001', 'ana@example.test');
        $page->callMountedAction();

        $this->assertSame(1, MemberModel::query()->count());
    }

    public function test_the_log_says_who_imported_and_how_many_without_row_data(): void
    {
        $admin = $this->actingAsPanelAdmin();
        $logged = [];
        Event::listen(MessageLogged::class, static function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message;
        });

        Livewire::test(ListMembers::class)
            ->callAction(ImportMembersAction::NAME, data: ['file' => $this->csv([['Ana', 'Pérez', 'ana@example.test', '600123456']])]);

        $panel = array_values(array_filter(
            $logged,
            static fn (MessageLogged $message): bool => $message->message === 'Memberships: members imported from the panel',
        ));
        $this->assertCount(1, $panel);
        $this->assertSame('info', $panel[0]->level);
        $this->assertSame($admin->id, $panel[0]->context['actor_id']);
        $this->assertSame(1, $panel[0]->context['created']);

        foreach ($logged as $message) {
            $line = $message->message.' '.json_encode($message->context);

            foreach (['ana@example.test', 'Pérez', '600123456'] as $personal) {
                $this->assertStringNotContainsString($personal, $line);
            }
        }
    }

    public function test_a_simulated_import_logs_who_really_ran_it(): void
    {
        $impersonator = UserModel::factory()->admin()->create();
        $this->actingAsPanelAdmin();
        $this->app->instance(ImpersonationContextInterface::class, new readonly class($impersonator) implements ImpersonationContextInterface
        {
            public function __construct(private UserModel $impersonator) {}

            public function isActive(): bool
            {
                return true;
            }

            public function impersonator(): Authenticatable
            {
                return $this->impersonator;
            }

            public function target(): ?Authenticatable
            {
                return auth()->user();
            }

            public function mode(): ImpersonationMode
            {
                return ImpersonationMode::Simulate;
            }
        });
        $logged = [];
        Event::listen(MessageLogged::class, static function (MessageLogged $message) use (&$logged): void {
            if ($message->message === 'Memberships: members imported from the panel') {
                $logged[] = $message->context;
            }
        });

        ImportMembersAction::import(['file' => [$this->temporaryUpload([['Ana', 'Pérez', 'ana@example.test']])], 'link_by_email' => false]);

        $this->assertCount(1, $logged);
        $this->assertSame($impersonator->id, $logged[0]['impersonator_id']);
        $this->assertTrue($logged[0]['simulated']);
    }

    public function test_the_import_is_not_marked_as_reading_but_the_template_is(): void
    {
        $action = ImportMembersAction::make();

        $this->assertFalse(ReadOnlyActions::allows($action));
        $this->assertTrue(ReadOnlyActions::allows(ImportMembersAction::templateAction()));
    }

    public function test_the_template_has_every_column_and_an_example_the_import_accepts(): void
    {
        $this->actingAsPanelAdmin();
        $path = tempnam(sys_get_temp_dir(), 'template');
        $this->assertIsString($path);

        try {
            file_put_contents($path, ImportMembersAction::templateBytes());
            $sheet = app(SpreadsheetReaderInterface::class)->read($path, new SpreadsheetLimitsDTO);
        } finally {
            @unlink($path);
        }

        $this->assertSame(
            array_map(static fn (MemberImportColumn $column): string => $column->label(), MemberImportColumn::cases()),
            $sheet->headers,
        );
        $this->assertCount(1, $sheet->rows);

        $preview = app(MemberImportServiceInterface::class)->preview(
            [new MemberImportRowDTO($sheet->rows[0]->number, array_map(strval(...), $sheet->rows[0]->values))],
            new MemberImportOptionsDTO,
        );
        $this->assertSame(1, $preview->newCount(), json_encode($preview->toArray()) ?: '');
        $this->assertTrue($preview->rows[0]->hasPeriod);
        $this->assertTrue($preview->rows[0]->hasFee);
    }

    public function test_a_template_download_forged_without_the_permission_gives_nothing(): void
    {
        $this->actingAs($this->editorWith(['memberships:members.view_any']));

        Livewire::test(ListMembers::class)
            ->set('mountedActions', [
                ['name' => ImportMembersAction::NAME, 'arguments' => [], 'context' => [], 'data' => ['link_by_email' => false]],
                ['name' => ImportMembersAction::TEMPLATE_NAME, 'arguments' => [], 'context' => ['schemaComponent' => 'mountedActionSchema0.template'], 'data' => []],
            ])
            ->call('callMountedAction')
            ->assertNoFileDownloaded();

        $this->assertNull(ImportMembersAction::downloadTemplate());
    }

    public function test_a_template_the_writer_cannot_build_is_a_notification_not_an_error(): void
    {
        $this->actingAsPanelAdmin();
        $this->app->instance(SpreadsheetWriterInterface::class, new class implements SpreadsheetWriterInterface
        {
            public function write(SpreadsheetTemplateDTO $template): string
            {
                throw SpreadsheetWriteException::failed();
            }
        });

        Livewire::test(ListMembers::class)
            ->callAction([TestAction::make(ImportMembersAction::NAME), TestAction::make(ImportMembersAction::TEMPLATE_NAME)->schemaComponent('template')])
            ->assertNoFileDownloaded()
            ->assertNotified(__('memberships::memberships.import.notifications.template_failed_title'));
    }

    public function test_the_template_downloads_as_an_xlsx(): void
    {
        $this->actingAsPanelAdmin();

        Livewire::test(ListMembers::class)
            ->callAction([TestAction::make(ImportMembersAction::NAME), TestAction::make(ImportMembersAction::TEMPLATE_NAME)->schemaComponent('template')])
            ->assertFileDownloaded(__('memberships::memberships.import.template.file_name'));
    }

    public function test_the_preview_counts_each_outcome_and_lists_what_will_not_be_created(): void
    {
        $html = $this->renderPreview(['preview' => new MemberImportPreviewDTO(
            rows: [
                new MemberImportRowReportDTO(line: 2, status: MemberImportRowStatus::New, memberNumber: '2025-0001', fullName: 'Ana Pérez'),
                new MemberImportRowReportDTO(line: 3, status: MemberImportRowStatus::Duplicate, duplicateReason: MemberImportDuplicateReason::Email, fullName: 'Luis García'),
                new MemberImportRowReportDTO(line: 4, status: MemberImportRowStatus::New, memberNumber: '2025-0002', fullName: 'Eva Ruiz', linkedUserId: (string) Str::uuid(), linkedUserName: 'Eva R.'),
                new MemberImportRowReportDTO(line: 5, status: MemberImportRowStatus::New, memberNumber: '2025-0003', fullName: 'Sara Gil', linkSkipReason: MemberImportLinkSkipReason::WouldRemoveAssociationRole),
            ],
            unknownColumns: ['Mascota'],
        ), 'error' => null]);

        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.new', 3, ['count' => 3])), $html);
        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.duplicate', 1, ['count' => 1])), $html);
        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.linked', 1, ['count' => 1])), $html);
        $this->assertStringContainsString('Luis García', $html);
        $this->assertStringContainsString(e(__('memberships::memberships.import.preview.duplicate_reason.email')), $html);
        $this->assertStringContainsString('Eva R.', $html);
        $this->assertStringContainsString(e(MemberImportLinkSkipReason::WouldRemoveAssociationRole->message()), $html);
        $this->assertStringContainsString('Mascota', $html);
        // A plain new row without anything to say is only counted
        $this->assertStringNotContainsString('Ana Pérez', $html);
    }

    public function test_the_preview_shows_each_error_under_its_column_and_says_nothing_will_be_imported(): void
    {
        $html = $this->renderPreview(['preview' => new MemberImportPreviewDTO(rows: [
            new MemberImportRowReportDTO(line: 7, status: MemberImportRowStatus::Invalid, errors: ['email' => ['No es un email válido.']], fullName: 'Ana Pérez'),
        ]), 'error' => null]);

        $this->assertStringContainsString(e(trans_choice('memberships::memberships.import.preview.invalid', 1, ['count' => 1])), $html);
        $this->assertStringContainsString(e(MemberImportColumn::Email->label()), $html);
        $this->assertStringContainsString(e('No es un email válido.'), $html);
        $this->assertStringContainsString(e(__('memberships::memberships.import.preview.blocked_by_errors')), $html);
    }

    public function test_the_import_permission_is_declared_without_a_default_role(): void
    {
        $permissions = (new MembershipsServiceProvider($this->app))->registerPermissions();
        $import = array_values(array_filter($permissions, static fn ($permission): bool => $permission->name === 'members.import'));

        $this->assertCount(1, $import);
        $this->assertSame([], $import[0]->roles);

        $manifest = json_decode((string) file_get_contents(__DIR__.'/../../../module.json'), true);
        $this->assertIsArray($manifest);
        $this->assertContains('members.import', $manifest['provides']['permissions']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderPreview(array $data): string
    {
        /** @var view-string $view */
        $view = 'memberships::filament.member-import-preview';

        return view($view, $data)->render();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): UploadedFile
    {
        $lines = ['Nombre;Apellidos;Email;Teléfono'];

        foreach ($rows as $row) {
            $lines[] = implode(';', $row);
        }

        return UploadedFile::fake()->createWithContent('socios.csv', implode("\n", $lines)."\n");
    }

    /**
     * Filament draws action modals outside the component's HTML: render the dialog itself.
     *
     * @param  Testable<ListMembers>  $page
     */
    private function dialogHtml(Testable $page): string
    {
        return $page->instance()->getSchema('mountedActionSchema0')?->toHtml() ?? '';
    }

    /**
     * A file in Livewire's temporary uploads, as the dialog's field holds it.
     *
     * @param  list<list<string>>  $rows
     */
    private function temporaryUpload(array $rows): TemporaryUploadedFile
    {
        $name = Str::random(40).'.csv';
        FileUploadConfiguration::storage()->putFileAs(FileUploadConfiguration::path(), $this->csv($rows), $name);

        return TemporaryUploadedFile::createFromLivewire($name);
    }

    /**
     * Records in serviceCalls what reaches the import service, answering as the real one.
     */
    private function spyImportService(): void
    {
        $real = app(MemberImportServiceInterface::class);
        $record = function (string $kind, int $rows): void {
            if ($kind === 'previews') {
                $this->serviceCalls['previews'][] = $rows;
            } else {
                $this->serviceCalls['imports'][] = $rows;
            }
        };

        $this->app->instance(MemberImportServiceInterface::class, new readonly class($real, $record) implements MemberImportServiceInterface
        {
            /**
             * @param  Closure(string, int): void  $record
             */
            public function __construct(private MemberImportServiceInterface $real, private Closure $record) {}

            public function preview(array $rows, MemberImportOptionsDTO $options): MemberImportPreviewDTO
            {
                ($this->record)('previews', count($rows));

                return $this->real->preview($rows, $options);
            }

            public function import(array $rows, MemberImportOptionsDTO $options): MemberImportResultDTO
            {
                ($this->record)('imports', count($rows));

                return $this->real->import($rows, $options);
            }
        });
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function submitDisabledFor(array $rows): bool
    {
        return $this->submitDisabled(Livewire::test(ListMembers::class)
            ->mountAction(ImportMembersAction::NAME)
            ->setActionData(['file' => $this->csv($rows)]));
    }

    /**
     * @param  Testable<ListMembers>  $page
     */
    private function submitDisabled(Testable $page): bool
    {
        // A fresh action, as when the dialog's buttons are drawn: its own data is not filled in
        // yet then, only the page's mounted state is
        $action = ImportMembersAction::make()->livewire($page->instance());

        return $action->getModalSubmitAction()?->isDisabled() ?? true;
    }

    /**
     * @return list<string>
     */
    private function temporaryUploads(): array
    {
        // The upload and the file Livewire keeps beside it with its name and size
        return array_values(FileUploadConfiguration::storage()->allFiles(FileUploadConfiguration::path()));
    }

    /**
     * @param  callable(): MemberImportResultDTO  $import
     */
    private function fakeImport(callable $import): void
    {
        $real = app(MemberImportServiceInterface::class);

        $this->app->instance(MemberImportServiceInterface::class, new readonly class($real, $import) implements MemberImportServiceInterface
        {
            /**
             * @param  callable(): MemberImportResultDTO  $import
             */
            public function __construct(private MemberImportServiceInterface $real, private mixed $import) {}

            public function preview(array $rows, MemberImportOptionsDTO $options): MemberImportPreviewDTO
            {
                return $this->real->preview($rows, $options);
            }

            public function import(array $rows, MemberImportOptionsDTO $options): MemberImportResultDTO
            {
                return ($this->import)();
            }
        });
    }

    private function member(string $number, string $email): MemberModel
    {
        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => $number,
            'first_name' => 'Luis',
            'last_name' => 'García',
            'email' => $email,
            'member_type' => 'regular',
            'status' => 'active',
            'joined_at' => '2025-01-01',
        ]);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function editorWith(array $permissionKeys): UserModel
    {
        $role = RoleModel::create(['name' => 'tester_'.uniqid(), 'display_name' => 'Permissions tester']);

        foreach ($permissionKeys as $key) {
            $permission = PermissionModel::firstOrCreate(
                ['key' => $key],
                ['label' => $key, 'resource' => 'members', 'action' => 'manage', 'module' => 'memberships'],
            );
            $role->permissions()->attach($permission->id);
        }

        $user = UserModel::factory()->editor()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
