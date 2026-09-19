<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CompanyInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Invitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = $this->invitation->company;

        return (new MailMessage)
            ->subject("You've been invited to join {$company->name}")
            ->line("{$company->name} has invited you to join their account as a {$this->invitation->role}.")
            ->action('Accept invitation', url("/invitations/{$this->invitation->token}"))
            ->line('This invitation expires in 7 days.');
    }
}
