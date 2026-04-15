<?php

namespace App\Notifications;

use App\Models\CreditApplication;
use App\Models\CreditFacility;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditApplicationDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CreditApplication $creditApplication, private readonly CreditFacility $creditFacility)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تحديث على طلب الرصيد')
            ->line('تم رفض طلب الرصيد من إحدى الجهات الممولة.')
            ->line($this->creditFacility->notes ?: 'يمكنك المتابعة مع Starcom إذا كنت تحتاج مراجعة إضافية.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'credit_application_declined',
            'application_id' => $this->creditApplication->id,
            'title'          => 'تحديث على طلب الرصيد',
            'message'        => $this->creditFacility->notes ?: 'تم رفض الطلب من إحدى الجهات الممولة.',
        ];
    }
}
