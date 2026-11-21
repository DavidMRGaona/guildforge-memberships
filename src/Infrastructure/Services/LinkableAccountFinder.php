<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Modules\Memberships\Application\DTOs\Import\LinkableAccountDTO;
use Modules\Memberships\Application\Services\LinkableAccountFinderInterface;
use Modules\Memberships\Infrastructure\Persistence\Eloquent\Models\MemberModel;

/**
 * Reads the core's accounts directly: the core's lookup by email compares the exact text
 * and leaves deactivated accounts out, so it cannot tell when an email is ambiguous.
 */
final readonly class LinkableAccountFinder implements LinkableAccountFinderInterface
{
    private const int LOOKUP_CHUNK = 500;

    /**
     * @param  list<string>  $emails
     * @return array<string, LinkableAccountDTO>
     */
    public function findByEmails(array $emails): array
    {
        $wanted = array_values(array_unique(array_map(
            static fn (string $email): string => mb_strtolower(trim($email)),
            $emails,
        )));

        /** @var array<string, list<UserModel>> $byEmail */
        $byEmail = [];

        foreach (array_chunk($wanted, self::LOOKUP_CHUNK) as $chunk) {
            // Deactivated and anonymized accounts too: they make an email ambiguous
            $accounts = UserModel::withTrashed()->whereIn(DB::raw('lower(email)'), $chunk)->get();

            foreach ($accounts as $account) {
                $byEmail[mb_strtolower((string) $account->email)][] = $account;
            }
        }

        $candidates = [];

        foreach ($wanted as $email) {
            $accounts = $byEmail[$email] ?? [];

            if (count($accounts) === 1 && $this->mayBeLinked($accounts[0])) {
                $candidates[$email] = $accounts[0];
            }
        }

        $linked = $this->alreadyLinked(array_map(
            static fn (UserModel $account): string => (string) $account->getKey(),
            array_values($candidates),
        ));

        $found = [];

        foreach ($candidates as $email => $account) {
            $id = (string) $account->getKey();

            if (! isset($linked[$id])) {
                $found[$email] = new LinkableAccountDTO($id, (string) ($account->display_name ?? $account->name));
            }
        }

        return $found;
    }

    private function mayBeLinked(UserModel $account): bool
    {
        return ! $account->trashed()
            && $account->anonymized_at === null
            && $account->email_verified_at !== null;
    }

    /**
     * Accounts already linked to a member, deleted members included.
     *
     * @param  list<string>  $userIds
     * @return array<string, true>
     */
    private function alreadyLinked(array $userIds): array
    {
        $linked = [];

        foreach (array_chunk($userIds, self::LOOKUP_CHUNK) as $chunk) {
            foreach (MemberModel::withTrashed()->whereIn('user_id', $chunk)->pluck('user_id') as $userId) {
                $linked[(string) $userId] = true;
            }
        }

        return $linked;
    }
}
