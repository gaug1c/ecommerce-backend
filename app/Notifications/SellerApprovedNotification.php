<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApprovedNotification extends Notification
{
    use Queueable;

    protected $sellerProfile;

    public function __construct($sellerProfile)
    {
        $this->sellerProfile = $sellerProfile;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'Votre demande vendeur a été approuvée',
            'shop_name' => $this->sellerProfile->shop_name
        ];
    }
}
