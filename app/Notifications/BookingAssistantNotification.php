<?php

namespace App\Notifications;

use App\Services\Ai\Communication\ComposedMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sent by TravelPlan AI. Sent from an already-queued job, so not queued itself.
 * The link only opens the authenticated chat: no token or personal data in the URL.
 */
class BookingAssistantNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ComposedMessage $message,
        public readonly string $type,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message->subject)
            ->greeting($this->message->greeting);

        if ($this->message->personalLine) {
            $mail->line($this->message->personalLine);
        }

        $mail->line($this->message->body);

        foreach ($this->message->facts as $label => $value) {
            $mail->line("**{$label} :** {$value}");
        }

        return $mail
            ->action($this->message->actionLabel, $this->message->actionUrl)
            ->line('Connectez-vous à TravelPlan pour poursuivre la conversation avec l\'assistant.')
            ->salutation("TravelPlan AI — assistant intelligent de réservation (message généré automatiquement)");
    }
}
