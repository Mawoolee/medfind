<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\NewAccountRegisteredNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Fans a "new account created" database notification out to every admin.
 *
 * Registration and admin pharmacy creation must never fail because of a
 * notification problem, so every failure is caught here and logged instead of
 * bubbling up to the caller.
 */
class AdminAccountNotifier
{
    /**
     * Notify all admins about a newly created account.
     *
     * @param  string       $accountName     Name of the new account holder (plain text).
     * @param  string|null  $accountEmail    Email of the new account holder, when known.
     * @param  string       $accountRole     Role of the new account, e.g. consumer/pharmacy.
     * @param  string|null  $pharmacyName    Pharmacy name when the account is a pharmacy.
     * @param  int|null     $excludeAdminId  Admin to skip (used when an admin triggers the creation).
     */
    public function notifyNewAccount(
        string $accountName,
        ?string $accountEmail = null,
        string $accountRole = 'consumer',
        ?string $pharmacyName = null,
        ?int $excludeAdminId = null,
    ): void {
        try {
            $admins = $this->admins($excludeAdminId);

            if ($admins->isEmpty()) {
                return;
            }

            $this->dispatch($admins, new NewAccountRegisteredNotification(
                accountName: $accountName,
                accountEmail: $accountEmail,
                accountRole: $accountRole,
                pharmacyName: $pharmacyName,
            ));
        } catch (Throwable $e) {
            // Swallowing is intentional (the caller's flow must complete) but it
            // is always recorded so the failure is diagnosable.
            Log::error('Failed to notify admins about a new account.', [
                'account_name' => $accountName,
                'account_role' => $accountRole,
                'pharmacy_name' => $pharmacyName,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
        }
    }

    /**
     * Every admin, optionally excluding the one who triggered the creation.
     *
     * @return Collection<int, User>
     */
    protected function admins(?int $excludeAdminId): Collection
    {
        return User::query()
            ->where('role', 'admin')
            ->when($excludeAdminId !== null, fn ($query) => $query->whereKeyNot($excludeAdminId))
            ->get();
    }

    /**
     * Seam for the actual send, kept separate so the failure path is testable.
     *
     * @param  Collection<int, User>  $admins
     */
    protected function dispatch(Collection $admins, NewAccountRegisteredNotification $notification): void
    {
        Notification::send($admins, $notification);
    }
}
