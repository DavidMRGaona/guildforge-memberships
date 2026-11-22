<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Filament\Pages\MembershipSettings;
use Modules\Memberships\Infrastructure\Activity\AnnouncesMemberYears;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Jobs\RepublishPermanentMemberYears;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Changing which member types are permanent changes the years of the active members of those
 * types without touching their records: the job tells the core about them.
 */
final class PermanentTypesChangeTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'memberships';

    protected bool $autoEnableModule = true;

    private int $numbers = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([], [MembershipSettings::class]);
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->travelTo(CarbonImmutable::parse('2026-06-15T12:00:00+02:00'));
    }

    public function test_saving_a_changed_setting_queues_the_job_for_the_types_added_or_removed(): void
    {
        Queue::fake();

        Livewire::test(MembershipSettings::class)
            ->fillForm(['permanent_member_types' => ['founder']])
            ->call('save')
            ->assertHasNoFormErrors();

        Queue::assertPushed(RepublishPermanentMemberYears::class, static function (RepublishPermanentMemberYears $job): bool {
            $types = $job->memberTypes;
            sort($types);

            return $types === ['founder', 'honorary'];
        });
    }

    public function test_saving_the_same_setting_queues_nothing(): void
    {
        Queue::fake();

        Livewire::test(MembershipSettings::class)
            ->fillForm(['permanent_member_types' => ['honorary']])
            ->call('save')
            ->assertHasNoFormErrors();

        Queue::assertNothingPushed();
    }

    public function test_the_job_announces_the_years_of_the_active_members_of_those_types_only(): void
    {
        $founder = UserModel::factory()->create();
        $honorary = UserModel::factory()->create();
        $inactive = UserModel::factory()->create();
        $this->memberOf($founder, MemberType::Founder, 'active', '2025-03-01 10:00:00');
        $this->memberOf($honorary, MemberType::Honorary, 'active', '2025-03-01 10:00:00');
        $this->memberOf($inactive, MemberType::Founder, 'inactive', '2025-03-01 10:00:00');
        $this->memberOf(null, MemberType::Founder, 'active', '2025-03-01 10:00:00');

        $events = Event::fake([ActivityRecorded::class]);
        $this->app->forgetInstance(ActivityPublisherInterface::class);

        (new RepublishPermanentMemberYears(['founder']))->handle($this->app->make(AnnouncesMemberYears::class));

        $ids = [];
        foreach ($events->dispatched(ActivityRecorded::class) as [$event]) {
            $ids[] = $event->subjectId;
        }
        sort($ids);

        $this->assertSame([$founder->id.':2025', $founder->id.':2026'], $ids);
    }

    private function memberOf(?UserModel $user, MemberType $type, string $status, string $joined): MemberModel
    {
        $this->numbers++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2020-%04d', $this->numbers),
            'first_name' => 'Ana',
            'last_name' => 'García',
            'member_type' => $type->value,
            'status' => $status,
            'user_id' => $user?->id,
            'joined_at' => $joined,
        ]);
    }
}
