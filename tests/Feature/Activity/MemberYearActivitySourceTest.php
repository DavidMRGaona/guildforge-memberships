<?php

declare(strict_types=1);

namespace Modules\Memberships\Tests\Feature\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Memberships\Domain\Enums\MembershipStatus;
use Modules\Memberships\Domain\Enums\MemberStatus;
use Modules\Memberships\Domain\Enums\MemberType;
use Modules\Memberships\Infrastructure\Activity\MemberYearActivitySource;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MembershipModel;
use Modules\Memberships\Infrastructure\Services\CalendarDate;
use Tests\Support\Activity\ActivitySourceTestCase;
use Tests\Support\Time\UsesUtcStorage;

/**
 * Years as a member (core#774): one subject per account and calendar year of the display zone
 * in which a period of the account's member record was in force.
 */
final class MemberYearActivitySourceTest extends ActivitySourceTestCase
{
    use UsesUtcStorage;

    protected ?string $moduleName = 'memberships';

    private int $numbers = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-06-15T12:00:00+02:00'));
    }

    protected function activitySource(): ActivitySourceInterface
    {
        return $this->app->make(MemberYearActivitySource::class);
    }

    protected function createCountedActivitySubject(): string
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');

        return $user->id.':2024';
    }

    protected function unknownActivitySubjectId(): string
    {
        return Str::uuid().':2024';
    }

    public function test_the_source_is_a_membership_year_of_the_member_role(): void
    {
        $this->assertSame('memberships.year', $this->activitySource()->type());
        $this->assertSame(ActivityKind::MembershipYear, $this->activitySource()->kind());
    }

    public function test_each_calendar_year_a_period_covers_is_a_subject_of_its_own(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-09-01', '2025-06-30');

        $source = $this->activitySource();

        $this->assertCount(1, $source->facts($user->id.':2024'));
        $this->assertCount(1, $source->facts($user->id.':2025'));
        $this->assertSame([], $source->facts($user->id.':2023'));
        $this->assertSame([], $source->facts($user->id.':2026'));

        $fact = $source->facts($user->id.':2024')[0];
        $this->assertSame($user->id, $fact->userId);
        $this->assertSame(ActivityRole::Member, $fact->role);
        $this->assertSame([], $fact->attributes);
    }

    public function test_the_fact_happened_the_first_day_the_account_was_a_member_that_year_in_the_display_zone(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-09-01', '2025-06-30');

        $source = $this->activitySource();

        $this->assertSame(CalendarDate::parse('2024-09-01')->getTimestamp(), $source->facts($user->id.':2024')[0]->occurredAt->getTimestamp());
        $this->assertSame(CalendarDate::parse('2025-01-01')->getTimestamp(), $source->facts($user->id.':2025')[0]->occurredAt->getTimestamp());
    }

    public function test_only_periods_that_were_in_force_count(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Pending, '2020-01-01', '2020-12-31');
        $this->period($member, MembershipStatus::Cancelled, '2021-01-01', '2021-12-31');
        $this->period($member, MembershipStatus::Active, '2022-01-01', '2022-12-31');
        $this->period($member, MembershipStatus::Expired, '2023-01-01', '2023-12-31');

        $source = $this->activitySource();

        $this->assertSame([], $source->facts($user->id.':2020'));
        $this->assertSame([], $source->facts($user->id.':2021'));
        $this->assertCount(1, $source->facts($user->id.':2022'));
        $this->assertCount(1, $source->facts($user->id.':2023'));
    }

    public function test_a_year_that_has_not_begun_is_not_reported(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Active, '2026-01-01', '2027-12-31');

        $source = $this->activitySource();

        $this->assertCount(1, $source->facts($user->id.':2026'));
        $this->assertSame([], $source->facts($user->id.':2027'));
    }

    public function test_a_period_that_has_not_started_yet_counts_for_nothing(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Active, '2026-07-01', '2026-12-31');

        $this->assertSame([], $this->activitySource()->facts($user->id.':2026'));
    }

    public function test_the_new_year_begins_at_midnight_of_the_display_zone(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Active, '2026-09-01', '2027-06-30');
        $source = $this->activitySource();

        // 23:30 on 31 December in Madrid (22:30 UTC): still 2026
        $this->travelTo(CarbonImmutable::parse('2026-12-31T23:30:00+01:00'));
        $before = $source->facts($user->id.':2027');

        // 00:30 on 1 January in Madrid (23:30 UTC on the 31st): 2027 has begun
        $this->travelTo(CarbonImmutable::parse('2027-01-01T00:30:00+01:00'));
        $after = $source->facts($user->id.':2027');

        $this->assertSame([], $before);
        $this->assertCount(1, $after);
    }

    public function test_with_utc_storage_the_year_still_begins_at_midnight_of_the_display_zone(): void
    {
        $this->useUtcStorage();
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Active, '2026-09-01', '2027-06-30');
        $source = $this->activitySource();

        // 23:30 UTC on 31 December is already 1 January in Madrid
        $this->travelTo(CarbonImmutable::parse('2026-12-31T23:30:00+00:00'));
        $begun = $source->facts($user->id.':2027');

        // 22:30 UTC on 31 December is 23:30 in Madrid: still 2026
        $this->travelTo(CarbonImmutable::parse('2026-12-31T22:30:00+00:00'));
        $notYet = $source->facts($user->id.':2027');

        $this->assertCount(1, $begun);
        $this->assertSame(CarbonImmutable::parse('2026-12-31T23:00:00+00:00')->getTimestamp(), $begun[0]->occurredAt->getTimestamp());
        $this->assertSame([], $notYet);
    }

    public function test_two_periods_or_two_records_of_one_account_are_one_subject_per_year(): void
    {
        $user = UserModel::factory()->create();
        $first = $this->memberOf($user);
        $second = $this->memberOf($user);
        $this->period($first, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $this->period($first, MembershipStatus::Expired, '2024-06-01', '2025-05-31');
        $this->period($second, MembershipStatus::Expired, '2024-03-01', '2024-12-31');

        $facts = $this->activitySource()->facts($user->id.':2024');

        $this->assertCount(1, $facts);
        $this->assertSame(CalendarDate::parse('2024-01-01')->getTimestamp(), $facts[0]->occurredAt->getTimestamp());
    }

    public function test_a_record_without_an_account_is_never_reported(): void
    {
        $member = $this->memberOf(null);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');

        $page = $this->activitySource()->history(null, 50);

        $this->assertSame([], $page->subjects);
    }

    public function test_a_deleted_record_no_longer_counts(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $member->delete();

        $this->assertSame([], $this->activitySource()->facts($user->id.':2024'));
        $this->assertSame([], $this->activitySource()->history(null, 50)->subjects);
    }

    public function test_the_state_of_the_record_does_not_change_the_years_it_was_a_member(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user, MemberStatus::Inactive);
        $this->period($member, MembershipStatus::Expired, '2024-01-01', '2024-12-31');

        $this->assertCount(1, $this->activitySource()->facts($user->id.':2024'));
    }

    public function test_an_id_that_is_not_an_account_and_a_year_has_no_facts(): void
    {
        $source = $this->activitySource();

        foreach (['', 'nope', 'nope:2024', (string) Str::uuid(), Str::uuid().':abc'] as $id) {
            $this->assertSame([], $source->facts($id));
        }
    }

    public function test_the_subject_id_is_read_in_lower_case(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');

        $this->assertCount(1, $this->activitySource()->facts(strtoupper($user->id).':2024'));
    }

    public function test_the_history_walks_account_years_in_a_stable_order_in_pages(): void
    {
        $expected = [];
        foreach (range(1, 3) as $ignored) {
            $user = UserModel::factory()->create();
            $this->period($this->memberOf($user), MembershipStatus::Expired, '2023-01-01', '2025-12-31');
            foreach ([2023, 2024, 2025] as $year) {
                $expected[] = $user->id.':'.$year;
            }
        }
        sort($expected);

        $source = $this->activitySource();
        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $source->history($cursor, 2);
            $this->assertLessThanOrEqual(2, count($page->subjects));
            foreach ($page->subjects as $subject) {
                $seen[] = $subject->subjectId;
            }
            $cursor = $page->nextCursor;
            $this->assertLessThan(50, ++$pages);
        } while ($cursor !== null);

        $this->assertSame($expected, $seen);
    }

    public function test_the_history_is_the_same_as_facts_for_every_subject(): void
    {
        $user = UserModel::factory()->create();
        $member = $this->memberOf($user);
        $this->period($member, MembershipStatus::Expired, '2022-09-01', '2023-06-30');
        $this->period($member, MembershipStatus::Active, '2025-09-01', '2026-06-30');

        $source = $this->activitySource();
        $page = $source->history(null, 50);

        $this->assertNull($page->nextCursor);
        $this->assertSame(
            [$user->id.':2022', $user->id.':2023', $user->id.':2025', $user->id.':2026'],
            array_map(static fn ($subject): string => $subject->subjectId, $page->subjects),
        );

        foreach ($page->subjects as $subject) {
            $this->assertEquals(
                array_map($this->comparable(...), $source->facts($subject->subjectId)),
                array_map($this->comparable(...), $subject->facts),
            );
        }
    }

    public function test_an_unreadable_cursor_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history('not-a-cursor', 10);
    }

    public function test_a_cursor_of_another_kind_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->activitySource()->history(rtrim(strtr(base64_encode('gt1|session|'.Str::uuid()), '+/', '-_'), '='), 10);
    }

    public function test_the_core_reads_it_and_drops_the_facts_of_an_anonymized_account(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $reader = $this->app->make(ActivityReaderInterface::class);

        $this->assertCount(1, $reader->facts('memberships.year', $user->id.':2024')->facts);

        UserModel::query()->whereKey($user->id)->update(['anonymized_at' => now()]);

        $this->assertSame([], $reader->facts('memberships.year', $user->id.':2024')->facts);
    }

    public function test_the_owner_sees_a_neutral_title_and_no_link(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $id = $user->id.':2024';

        $described = $this->activitySource()->describe([$id], $user);

        $this->assertSame(['Socio en 2024'], array_values(array_map(static fn ($subject): string => $subject->title, $described)));
        $this->assertNull($described[$id]->url);
    }

    public function test_whoever_manages_members_sees_it(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $id = $user->id.':2024';

        $described = $this->activitySource()->describe([$id], UserModel::factory()->admin()->create());

        $this->assertArrayHasKey($id, $described);
        $this->assertNull($described[$id]->url);
    }

    public function test_nobody_else_sees_it_neither_a_guest(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');
        $id = $user->id.':2024';
        $source = $this->activitySource();

        $this->assertSame([], $source->describe([$id], UserModel::factory()->create()));
        $this->assertSame([], $source->describe([$id], null));
    }

    public function test_it_describes_only_years_that_count(): void
    {
        $user = UserModel::factory()->create();
        $this->period($this->memberOf($user), MembershipStatus::Expired, '2024-01-01', '2024-12-31');

        $described = $this->activitySource()->describe([$user->id.':2023', $user->id.':2024', 'nope'], $user);

        $this->assertSame([$user->id.':2024'], array_map(static fn ($subject): string => $subject->subjectId, array_values($described)));
    }

    private function memberOf(?UserModel $user, MemberStatus $status = MemberStatus::Active): MemberModel
    {
        $this->numbers++;

        return MemberModel::create([
            'id' => (string) Str::uuid(),
            'member_number' => sprintf('2020-%04d', $this->numbers),
            'first_name' => 'Ana',
            'last_name' => 'García',
            'member_type' => MemberType::Regular->value,
            'status' => $status->value,
            'user_id' => $user?->id,
            'joined_at' => '2020-01-01 00:00:00',
        ]);
    }

    private function period(MemberModel $member, MembershipStatus $status, string $start, string $end): MembershipModel
    {
        return MembershipModel::create([
            'id' => (string) Str::uuid(),
            'member_id' => $member->id,
            'period_type' => 'rolling',
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status->value,
        ]);
    }

    /**
     * @return array{string, string, int}
     */
    private function comparable(ActivityFactDTO $fact): array
    {
        return [$fact->userId, $fact->role->value, $fact->occurredAt->getTimestamp()];
    }
}
