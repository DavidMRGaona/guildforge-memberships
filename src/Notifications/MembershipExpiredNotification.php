<?php

declare(strict_types=1);

namespace Modules\Memberships\Notifications;

use App\Application\Mail\Contracts\HasMailCategory;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Memberships\MembershipsServiceProvider;
use Modules\Memberships\Notifications\Concerns\LinksToMemberSituation;

final class MembershipExpiredNotification extends Notification implements HasMailCategory, HasSiteNotification, ShouldQueue
{
    use LinksToMemberSituation;
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly DateTimeImmutable $endDate,
    ) {}

    public function mailCategory(): string
    {
        return MembershipsServiceProvider::MAIL_FEES;
    }

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('memberships::notifications.membership_expired.subject'))
            ->greeting(__('memberships::notifications.membership_expired.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.membership_expired.line1', [
                // A calendar date built in the display zone (CalendarDate), so this is the site's day
                'date' => $this->endDate->format('d/m/Y'),
            ]))
            ->line(__('memberships::notifications.membership_expired.line2'))
            ->action(
                $this->situationActionText($notifiable, 'memberships::notifications.membership_expired.action'),
                $this->situationUrl($notifiable)
            )
            ->salutation(__('memberships::notifications.membership_expired.salutation'));
    }

    /**
     * The bell notice: the same facts as the mail, with the site's own page instead of a host.
     */
    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __('memberships::notifications.membership_expired.site.title'),
            url: $this->situationUrl($notifiable, absolute: false),
            body: __('memberships::notifications.membership_expired.site.body', [
                'date' => $this->endDate->format('d/m/Y'),
            ]),
            icon: 'id-card',
        );
    }
}
