<?php

declare(strict_types=1);

namespace Modules\Memberships\Notifications\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Memberships\MembershipsServiceProvider;

/**
 * Where a membership notice sends the person: an account opens the profile's member tab,
 * which shows their situation; a member without account (the mail goes to the address on the
 * record) gets the association's page, the only one they can open.
 */
trait LinksToMemberSituation
{
    private function situationUrl(object $notifiable, bool $absolute = true): string
    {
        return $notifiable instanceof Authenticatable
            ? route('profile.show', ['tab' => MembershipsServiceProvider::PROFILE_TAB], $absolute)
            : route('about', absolute: $absolute);
    }

    /**
     * @param  string  $contactKey  the notice's own «contact the association» text
     */
    private function situationActionText(object $notifiable, string $contactKey): string
    {
        return (string) __($notifiable instanceof Authenticatable ? 'memberships::notifications.see_situation' : $contactKey);
    }
}
