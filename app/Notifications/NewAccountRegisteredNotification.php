<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database-only notification sent to admins whenever a new account is created.
 *
 * The payload follows the shape rendered by resources/views/notifications/index.blade.php:
 * `title`, `message`, `url` and `type`. Values are stored as raw plain text —
 * the view escapes them with `{{ }}`, so no HTML is embedded here.
 */
class NewAccountRegisteredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $accountName,
        public readonly ?string $accountEmail = null,
        public readonly string $accountRole = 'consumer',
        public readonly ?string $pharmacyName = null,
    ) {}

    /**
     * Database channel only — admins read these from the in-app notifications page.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->pharmacyName !== null
            ? $this->pharmacyPayload()
            : $this->consumerPayload();
    }

    /**
     * Pharmacy accounts are the case admins must act on: the payload names the
     * pharmacy, credits the owner, and deep-links to the requirements review
     * page. `pending` renders the clock icon in the notifications view.
     */
    private function pharmacyPayload(): array
    {
        return [
            'title' => 'New pharmacy account: '.$this->pharmacyName,
            'message' => $this->owner().' registered the pharmacy "'.$this->pharmacyName.'". It needs requirements review.',
            'url' => route('admin.requirements'),
            'type' => 'pending',
        ];
    }

    /**
     * Consumer (and any other non-pharmacy) accounts are informational only, so
     * they link to the user list and use the generic bell icon.
     */
    private function consumerPayload(): array
    {
        return [
            'title' => 'New account registered: '.$this->accountName,
            'message' => $this->owner().' created a new '.$this->accountRole.' account.',
            'url' => route('admin.users'),
            'type' => 'account',
        ];
    }

    /**
     * "Name (email)" when an email is known, otherwise just the name.
     */
    private function owner(): string
    {
        return $this->accountEmail !== null && $this->accountEmail !== ''
            ? $this->accountName.' ('.$this->accountEmail.')'
            : $this->accountName;
    }
}
