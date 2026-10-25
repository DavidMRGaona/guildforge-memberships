<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Memberships\Domain\Enums\MemberType;

/**
 * @property Schema $form
 */
final class MembershipSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationGroup(): string
    {
        return __('memberships::memberships.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('memberships::memberships.navigation.settings');
    }

    public function getTitle(): string
    {
        return __('memberships::memberships.settings.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(ModuleManagerServiceInterface $moduleManager): void
    {
        $settings = $moduleManager->getSettings(new ModuleName('memberships'));

        // Load settings with defaults from config
        $defaults = config('memberships');

        $this->form->fill(array_merge($defaults, $settings));
    }

    /**
     * Get the form schema components for settings.
     *
     * @return array<Component>
     */
    public static function getFormSchemaComponents(): array
    {
        return [
            Section::make(__('memberships::memberships.settings.sections.membership_period'))
                ->schema([
                    Select::make('default_period_type')
                        ->label(__('memberships::memberships.settings.fields.default_period_type'))
                        ->helperText(__('memberships::memberships.settings.fields.default_period_type_help'))
                        ->options([
                            'calendar_year' => __('memberships::memberships.enums.membership_period_type.calendar_year'),
                            'academic_year' => __('memberships::memberships.enums.membership_period_type.academic_year'),
                            'rolling' => __('memberships::memberships.enums.membership_period_type.rolling'),
                        ])
                        ->default('calendar_year')
                        ->required(),

                    Select::make('academic_start_month')
                        ->label(__('memberships::memberships.settings.fields.academic_start_month'))
                        ->helperText(__('memberships::memberships.settings.fields.academic_start_month_help'))
                        ->options([
                            1 => __('memberships::memberships.settings.months.1'),
                            2 => __('memberships::memberships.settings.months.2'),
                            3 => __('memberships::memberships.settings.months.3'),
                            4 => __('memberships::memberships.settings.months.4'),
                            5 => __('memberships::memberships.settings.months.5'),
                            6 => __('memberships::memberships.settings.months.6'),
                            7 => __('memberships::memberships.settings.months.7'),
                            8 => __('memberships::memberships.settings.months.8'),
                            9 => __('memberships::memberships.settings.months.9'),
                            10 => __('memberships::memberships.settings.months.10'),
                            11 => __('memberships::memberships.settings.months.11'),
                            12 => __('memberships::memberships.settings.months.12'),
                        ])
                        ->default(9)
                        ->required(),
                ])
                ->columns(2),

            Section::make(__('memberships::memberships.settings.sections.role_integration'))
                ->schema([
                    Toggle::make('enable_role_assignment')
                        ->label(__('memberships::memberships.settings.fields.enable_role_assignment'))
                        ->helperText(__('memberships::memberships.settings.fields.enable_role_assignment_help'))
                        ->default(false),
                ])
                ->columns(1),

            Section::make(__('memberships::memberships.settings.sections.permanent_members'))
                ->schema([
                    CheckboxList::make('permanent_member_types')
                        ->label(__('memberships::memberships.settings.fields.permanent_member_types'))
                        ->helperText(__('memberships::memberships.settings.fields.permanent_member_types_help'))
                        ->options(self::memberTypeOptions())
                        ->default([MemberType::Honorary->value])
                        ->columns(3),
                ])
                ->columns(1),

            Section::make(__('memberships::memberships.settings.sections.notifications'))
                ->schema([
                    TextInput::make('expiration_warning_days')
                        ->label(__('memberships::memberships.settings.fields.expiration_warning_days'))
                        ->helperText(__('memberships::memberships.settings.fields.expiration_warning_days_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),

                    Toggle::make('send_expired_email')
                        ->label(__('memberships::memberships.settings.fields.send_expired_email'))
                        ->helperText(__('memberships::memberships.settings.fields.send_expired_email_help'))
                        ->default(false),

                    Toggle::make('send_overdue_email')
                        ->label(__('memberships::memberships.settings.fields.send_overdue_email'))
                        ->helperText(__('memberships::memberships.settings.fields.send_overdue_email_help'))
                        ->default(false),
                ])
                ->columns(2),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function memberTypeOptions(): array
    {
        $options = [];

        foreach (MemberType::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchemaComponents())
            ->statePath('data');
    }

    public function save(ModuleManagerServiceInterface $moduleManager): void
    {
        $formData = $this->form->getState();

        $moduleManager->updateSettings(new ModuleName('memberships'), $formData);

        Notification::make()
            ->title(__('memberships::memberships.settings.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('common.save'))
                ->submit('save'),
        ];
    }
}
