<?php

declare(strict_types=1);

namespace Modules\Memberships\Notifications;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MembershipExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly DateTimeImmutable $endDate,
        private readonly int $daysUntilExpiration,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $formattedDate = $this->endDate->format('d/m/Y');

        return (new MailMessage())
            ->subject(__('memberships::notifications.membership_expiring.subject'))
            ->greeting(__('memberships::notifications.membership_expiring.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.membership_expiring.line1', [
                'days' => $this->daysUntilExpiration,
                'date' => $formattedDate,
            ]))
            ->line(__('memberships::notifications.membership_expiring.line2'))
            ->action(__('memberships::notifications.membership_expiring.action'), url('/'))
            ->salutation(__('memberships::notifications.membership_expiring.salutation'));
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
