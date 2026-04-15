<?php

namespace App\Notifications;

use App\Libraries\AppLibrary;
use App\Models\CreditApplication;
use App\Models\CreditFacility;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditApplicationApprovedNotification extends Notification
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
            ->subject('تم إضافة رصيد إلى محفظتك')
            ->line('تمت الموافقة على طلب الرصيد الخاص بك.')
            ->line('تمت إضافة ' . AppLibrary::currencyAmountFormat($this->creditFacility->approved_amount) . ' إلى محفظتك.')
            ->line('يمكنك الآن استخدام خيار اشتري بالآجل.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'credit_application_approved',
            'application_id' => $this->creditApplication->id,
            'facility_id'    => $this->creditFacility->id,
            'title'          => 'تم إضافة رصيد إلى محفظتك',
            'message'        => 'تمت إضافة ' . AppLibrary::currencyAmountFormat($this->creditFacility->approved_amount) . ' إلى محفظتك.',
        ];
    }
}
