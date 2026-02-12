<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SellerRejectedNotification extends Notification
{
    use Queueable;

    protected $sellerProfile;
    protected $reason;

    public function __construct($sellerProfile, $reason)
    {
        $this->sellerProfile = $sellerProfile;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Votre demande vendeur a été refusée',
            'shop_name' => $this->sellerProfile->shop_name,
            'reason' => $this->reason
        ];
    }
}
