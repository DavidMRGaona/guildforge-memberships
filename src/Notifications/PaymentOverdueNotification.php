<?php

declare(strict_types=1);

namespace Modules\Memberships\Notifications;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly string $formattedAmount,
        private readonly DateTimeImmutable $dueDate,
        private readonly int $daysOverdue,
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
            ->subject(__('memberships::notifications.payment_overdue.subject'))
            ->greeting(__('memberships::notifications.payment_overdue.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.payment_overdue.line1', [
                'amount' => $this->formattedAmount,
                'date' => $this->dueDate->format('d/m/Y'),
            ]))
            ->line(__('memberships::notifications.payment_overdue.line2', [
                'days' => $this->daysOverdue,
            ]))
            ->action(
                __('memberships::notifications.payment_overdue.action'),
                url('/')
            )
            ->salutation(__('memberships::notifications.payment_overdue.salutation'));
    }
}
