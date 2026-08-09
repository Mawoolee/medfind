<?php

namespace App\Notifications;

use App\Models\Pharmacy;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PharmacyStatusNotification extends Notification
{
    use Queueable;

    public Pharmacy $pharmacy;
    public string $status;

    public function __construct(Pharmacy $pharmacy, string $status)
    {
        $this->pharmacy = $pharmacy;
        $this->status = $status;
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->status === 'approved';

        return (new MailMessage)
            ->subject($approved ? 'Your Pharmacy Has Been Approved' : 'Your Pharmacy Application Status')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your pharmacy "' . $this->pharmacy->pharmacy_name . '" has been ' . $this->status . '.')
            ->line($approved
                ? 'You can now log in and manage your inventory.'
                : 'If you have questions, please contact support.')
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('Thank you for using MedFind!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'pharmacy_id'   => $this->pharmacy->id,
            'pharmacy_name' => $this->pharmacy->pharmacy_name,
            'status'        => $this->status,
            'message'       => 'Your pharmacy "' . $this->pharmacy->pharmacy_name . '" has been ' . $this->status . '.',
        ];
    }
}
