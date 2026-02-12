<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class NewSellerRequestNotification extends Notification
{
    use Queueable;

    protected $sellerProfile;

    public function __construct($sellerProfile)
    {
        $this->sellerProfile = $sellerProfile;
    }

    public function via($notifiable)
    {
        return ['database']; // stockage en DB
    }

    public function toDatabase($notifiable)
    {
        return [
            'seller_profile_id' => $this->sellerProfile->id,
            'user_id'           => $this->sellerProfile->user_id,
            'shop_name'         => $this->sellerProfile->shop_name,
            'message'           => 'Nouvelle demande vendeur à valider'
        ];
    }
}
