<?php

declare(strict_types=1);

namespace Modules\Memberships\Infrastructure\Activity;

use InvalidArgumentException;

/**
 * The opaque cursor of the member years history (core#774): the account and year of the last
 * subject of a page, marked with this source and encoded. Anything else throws, so a walk never
 * starts again from the top and a cursor of another source is never read as one of this one.
 */
final class MemberYearCursor
{
    private const string PREFIX = 'mb1|';

    public static function after(string $userId, int $year): string
    {
        return rtrim(strtr(base64_encode(self::PREFIX.MemberYears::subjectId($userId, $year)), '+/', '-_'), '=');
    }

    /**
     * @return array{string, int} the account and the year the next page starts after
     *
     * @throws InvalidArgumentException when it is not a cursor of this source
     */
    public static function read(string $cursor): array
    {
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if (! is_string($decoded) || ! str_starts_with($decoded, self::PREFIX)) {
            throw new InvalidArgumentException('The member years history cursor cannot be read.');
        }

        return MemberYears::parseSubjectId(substr($decoded, strlen(self::PREFIX)))
            ?? throw new InvalidArgumentException('The member years history cursor cannot be read.');
    }
}
