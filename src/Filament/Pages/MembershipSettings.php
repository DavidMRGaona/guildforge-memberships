<?php

declare(strict_types=1);

namespace Modules\Memberships\Filament\Pages;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * @property Form $form
 */
final class MembershipSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.simple-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function getNavigationGroup(): ?string
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
     * @return array<\Filament\Forms\Components\Component>
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
                        ->default(false)
                        ->reactive(),

                    TextInput::make('member_role_name')
                        ->label(__('memberships::memberships.settings.fields.member_role_name'))
                        ->helperText(__('memberships::memberships.settings.fields.member_role_name_help'))
                        ->default('member')
                        ->maxLength(100)
                        ->visible(fn (callable $get): bool => (bool) $get('enable_role_assignment')),
                ])
                ->columns(2),

            Section::make(__('memberships::memberships.settings.sections.grace_and_notifications'))
                ->schema([
                    TextInput::make('grace_period_days')
                        ->label(__('memberships::memberships.settings.fields.grace_period_days'))
                        ->helperText(__('memberships::memberships.settings.fields.grace_period_days_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),

                    TextInput::make('expiration_warning_days')
                        ->label(__('memberships::memberships.settings.fields.expiration_warning_days'))
                        ->helperText(__('memberships::memberships.settings.fields.expiration_warning_days_help'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(365)
                        ->default(30)
                        ->required(),
                ])
                ->columns(2),

            Section::make(__('memberships::memberships.settings.sections.fees'))
                ->schema([
                    Toggle::make('enable_proration')
                        ->label(__('memberships::memberships.settings.fields.enable_proration'))
                        ->helperText(__('memberships::memberships.settings.fields.enable_proration_help'))
                        ->default(true),

                    TextInput::make('default_currency')
                        ->label(__('memberships::memberships.settings.fields.default_currency'))
                        ->helperText(__('memberships::memberships.settings.fields.default_currency_help'))
                        ->default('EUR')
                        ->maxLength(3)
                        ->required(),
                ])
                ->columns(2),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(self::getFormSchemaComponents())
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
