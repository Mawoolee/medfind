<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use App\Notifications\NewAccountRegisteredNotification;
use App\Services\AdminAccountNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Admins must always learn about newly created accounts — especially pharmacy
 * accounts, which are created `pending` and need requirements review.
 */
class AdminNewAccountNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $email): User
    {
        return User::factory()->create(['role' => 'admin', 'email' => $email]);
    }

    /**
     * Swap the notifier for one whose send step always blows up, so the
     * surrounding flow can be checked against a failing dispatch.
     */
    private function swapInFailingNotifier(): void
    {
        $this->app->instance(AdminAccountNotifier::class, new class extends AdminAccountNotifier
        {
            protected function dispatch(Collection $admins, NewAccountRegisteredNotification $notification): void
            {
                throw new RuntimeException('notification channel exploded');
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Consumer self-registration
    // ─────────────────────────────────────────────────────────────────────────

    public function test_consumer_registration_notifies_every_admin_and_nobody_else(): void
    {
        $adminOne = $this->admin('admin1@example.com');
        $adminTwo = $this->admin('admin2@example.com');
        $consumer = User::factory()->create(['role' => 'consumer']);
        $pharmacyUser = User::factory()->create(['role' => 'pharmacy']);

        $this->post('/register', [
            'name' => 'Nina Consumer',
            'email' => 'nina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('home'));

        $newUser = User::where('email', 'nina@example.com')->firstOrFail();

        foreach ([$adminOne, $adminTwo] as $admin) {
            $this->assertSame(1, $admin->unreadNotifications()->count());

            $data = $admin->notifications()->firstOrFail()->data;
            $this->assertStringContainsString('Nina Consumer', $data['title']);
            $this->assertStringContainsString('nina@example.com', $data['message']);
            $this->assertSame(route('admin.users'), $data['url']);
            $this->assertSame('account', $data['type']);
        }

        $this->assertSame(0, $consumer->notifications()->count());
        $this->assertSame(0, $pharmacyUser->notifications()->count());
        $this->assertSame(0, $newUser->notifications()->count());

        $this->assertDatabaseHas('notifications', [
            'type' => NewAccountRegisteredNotification::class,
            'notifiable_id' => $adminOne->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pharmacy self-registration
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pharmacy_registration_notifies_admins_with_pharmacy_name_and_requirements_link(): void
    {
        $admin = $this->admin('admin@example.com');

        // Step 1: account details are only parked in the session.
        $this->post(route('register.pharmacy.account'), [
            'name' => 'Owner Ortiz',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        $this->assertSame(0, $admin->notifications()->count());

        // Step 2: this is where the user + pharmacy are actually created.
        $this->post(route('register.pharmacy.store'), [
            'pharmacy_name' => 'Albay Medical Center Pharmacy',
            'pharmacyAddress' => '123 Rizal St, Legazpi City',
            'contactNumber' => '09123456789',
        ])->assertRedirect(route('pharmacy.requirements'));

        $this->assertSame(1, $admin->unreadNotifications()->count());

        $data = $admin->notifications()->firstOrFail()->data;
        $this->assertStringContainsString('Albay Medical Center Pharmacy', $data['title']);
        $this->assertStringContainsString('Albay Medical Center Pharmacy', $data['message']);
        $this->assertStringContainsString('Owner Ortiz', $data['message']);
        $this->assertStringContainsString('owner@example.com', $data['message']);
        $this->assertStringContainsString('requirements review', $data['message']);
        $this->assertSame(route('admin.requirements'), $data['url']);
        // `pending` is the key the notifications view maps to the clock icon.
        $this->assertSame('pending', $data['type']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin-created pharmacy
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_created_pharmacy_notifies_other_admins_but_not_the_acting_admin(): void
    {
        $actingAdmin = $this->admin('acting@example.com');
        $otherAdmin = $this->admin('other@example.com');
        $owner = User::factory()->create(['role' => 'pharmacy', 'email' => 'shopowner@example.com', 'name' => 'Shop Owner']);

        $this->actingAs($actingAdmin)
            ->post(route('admin.pharmacy.store'), [
                'pharmacy_name' => 'Legazpi Central Pharmacy',
                'pharmacyAddress' => '45 Peñaranda St, Legazpi City',
                'contactNumber' => '09987654321',
                'user_id' => $owner->id,
            ])
            ->assertRedirect(route('admin.pharmacies'));

        $this->assertSame(0, $actingAdmin->notifications()->count());
        $this->assertSame(1, $otherAdmin->unreadNotifications()->count());

        $data = $otherAdmin->notifications()->firstOrFail()->data;
        $this->assertStringContainsString('Legazpi Central Pharmacy', $data['title']);
        $this->assertStringContainsString('Shop Owner', $data['message']);
        $this->assertSame(route('admin.requirements'), $data['url']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // A broken notification must never break the surrounding flow
    // ─────────────────────────────────────────────────────────────────────────

    public function test_consumer_registration_succeeds_and_logs_when_notification_dispatch_fails(): void
    {
        $this->admin('admin@example.com');
        Log::spy();
        $this->swapInFailingNotifier();

        $this->post('/register', [
            'name' => 'Nina Consumer',
            'email' => 'nina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'nina@example.com', 'role' => 'consumer']);
        $this->assertDatabaseCount('notifications', 0);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'Failed to notify admins')
                && ($context['exception'] ?? null) === 'notification channel exploded')
            ->once();
    }

    public function test_pharmacy_registration_succeeds_when_notification_dispatch_fails(): void
    {
        $this->admin('admin@example.com');
        $this->swapInFailingNotifier();

        $this->post(route('register.pharmacy.account'), [
            'name' => 'Owner Ortiz',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('register.pharmacy.details'));

        $this->post(route('register.pharmacy.store'), [
            'pharmacy_name' => 'Albay Medical Center Pharmacy',
            'pharmacyAddress' => '123 Rizal St, Legazpi City',
        ])->assertRedirect(route('pharmacy.requirements'));

        $this->assertAuthenticated();

        $user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame('pharmacy', $user->role);

        $pharmacy = Pharmacy::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Albay Medical Center Pharmacy', $pharmacy->pharmacy_name);
        $this->assertSame('pending', $pharmacy->status);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // No admins at all is a no-op, not an error
    // ─────────────────────────────────────────────────────────────────────────

    public function test_registration_works_when_there_are_no_admins(): void
    {
        $this->post('/register', [
            'name' => 'Nina Consumer',
            'email' => 'nina@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'nina@example.com']);
        $this->assertDatabaseCount('notifications', 0);
    }
}
