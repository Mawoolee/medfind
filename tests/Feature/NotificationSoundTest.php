<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationSoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_endpoint_also_returns_total_notification_count(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test.notification',
            'data' => ['title' => 'Unread'],
        ]);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test.notification',
            'data' => ['title' => 'Read'],
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('notification_total', 2);
    }

    public function test_notifications_page_lists_all_notifications_without_pagination_controls(): void
    {
        $user = User::factory()->create();
        for ($index = 1; $index <= 21; $index++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test.notification',
                'data' => ['title' => 'Notification '.$index],
            ]);
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notification 1')
            ->assertSee('Notification 21')
            ->assertDontSee('aria-label="Pagination Navigation"', false);
    }
}
