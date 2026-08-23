<?php

namespace App\Notifications;

use App\Models\CreditApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CreditApplicationAmendedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly CreditApplication $creditApplication)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'credit_application_amended',
            'application_id' => $this->creditApplication->id,
            'title' => 'تم استكمال بيانات طلب التمويل',
            'message' => 'تم تعديل طلب العميل ' . ($this->creditApplication->user?->name ?: 'العميل') . ' وإعادته إلى طلبات الشراء بالآجل للمراجعة.',
            'url' => '/admin/credit-requests/' . $this->creditApplication->id,
        ];
    }
}
