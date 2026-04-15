<?php

namespace App\Notifications;

use App\Models\CreditApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCreditApplicationSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CreditApplication $creditApplication)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('طلب حد ائتماني جديد')
            ->line('تم تقديم طلب حد ائتماني جديد من عميل لدى Starcom.')
            ->line('اسم العميل: ' . $this->creditApplication->user?->name)
            ->line('يرجى مراجعة الطلب من لوحة التمويل.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'credit_application_submitted',
            'application_id' => $this->creditApplication->id,
            'title'          => 'طلب حد ائتماني جديد',
            'message'        => 'تم استلام طلب جديد من العميل ' . $this->creditApplication->user?->name,
        ];
    }
}
