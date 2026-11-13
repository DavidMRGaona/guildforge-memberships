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

final class PaymentOverdueNotification extends Notification implements HasMailCategory, HasSiteNotification, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $memberName,
        private readonly string $formattedAmount,
        private readonly DateTimeImmutable $dueDate,
        private readonly int $daysOverdue,
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
            ->subject(__('memberships::notifications.payment_overdue.subject'))
            ->greeting(__('memberships::notifications.payment_overdue.greeting', ['name' => $this->memberName]))
            ->line(__('memberships::notifications.payment_overdue.line1', [
                'amount' => $this->formattedAmount,
                // A calendar date built in the display zone (CalendarDate), so this is the site's day
                'date' => $this->dueDate->format('d/m/Y'),
            ]))
            ->line(__('memberships::notifications.payment_overdue.line2', [
                'days' => $this->daysOverdue,
            ]))
            ->line(__('memberships::notifications.payment_overdue.line3'))
            ->action(
                __('memberships::notifications.payment_overdue.action'),
                route('about')
            )
            ->salutation(__('memberships::notifications.payment_overdue.salutation'));
    }

    /**
     * The bell notice: the same facts as the mail, with the site's own page instead of a host.
     */
    public function toSiteNotification(object $notifiable): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: __('memberships::notifications.payment_overdue.site.title'),
            url: route('about', absolute: false),
            body: __('memberships::notifications.payment_overdue.site.body', [
                'amount' => $this->formattedAmount,
                'date' => $this->dueDate->format('d/m/Y'),
                'days' => $this->daysOverdue,
            ]),
            icon: 'credit-card',
        );
    }
}
