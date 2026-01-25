<?php

declare(strict_types=1);

namespace Modules\Memberships\Notifications;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MembershipExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly DateTimeImmutable $endDate,
    ) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(__('memberships::notifications.membership_expired.subject'))
            ->greeting(__('memberships::notifications.membership_expired.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.membership_expired.line1', [
                'date' => $this->endDate->format('d/m/Y'),
            ]))
            ->line(__('memberships::notifications.membership_expired.line2'))
            ->action(
                __('memberships::notifications.membership_expired.action'),
                url('/')
            )
            ->salutation(__('memberships::notifications.membership_expired.salutation'));
    }
}
