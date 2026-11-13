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

final class MembershipExpiringNotification extends Notification implements HasMailCategory, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly DateTimeImmutable $endDate,
        private readonly int $daysUntilExpiration,
    ) {}

    public function mailCategory(): string
    {
        return MembershipsServiceProvider::MAIL_EXPIRY_NOTICES;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', HasSiteNotification::CHANNEL];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // A calendar date built in the display zone (CalendarDate), so this is the site's day
        $formattedDate = $this->endDate->format('d/m/Y');

        return (new MailMessage)
            ->subject(__('memberships::notifications.membership_expiring.subject'))
            ->greeting(__('memberships::notifications.membership_expiring.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.membership_expiring.line1', [
                'days' => $this->daysUntilExpiration,
                'date' => $formattedDate,
            ]))
            ->line(__('memberships::notifications.membership_expiring.line2'))
            ->action(__('memberships::notifications.membership_expiring.action'), route('about'))
            ->salutation(__('memberships::notifications.membership_expiring.salutation'));
    }

    /**
     * The bell notice: the same facts as the mail, with the site's own page instead of a host.
     */
    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __('memberships::notifications.membership_expiring.site.title'),
            url: route('about', absolute: false),
            body: __('memberships::notifications.membership_expiring.site.body', [
                'days' => $this->daysUntilExpiration,
                'date' => $this->endDate->format('d/m/Y'),
            ]),
            icon: 'id-card',
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'member_name' => $this->memberName,
            'end_date' => $this->endDate->format('Y-m-d'),
            'days_until_expiration' => $this->daysUntilExpiration,
        ];
    }
}
