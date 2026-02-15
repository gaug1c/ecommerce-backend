<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    protected $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $frontendUrl = config('app.frontend_url') .
            "/reset-password?token={$this->token}&email=" . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting('Bonjour,')
            ->line('Nous avons reçu une demande de réinitialisation de votre mot de passe.')
            ->action('Réinitialiser mon mot de passe', $frontendUrl)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line('Si vous n\'êtes pas à l\'origine de cette demande, aucune action n\'est requise.')
            ->salutation('Cordialement, L\'équipe Agrolink');
    }
}
