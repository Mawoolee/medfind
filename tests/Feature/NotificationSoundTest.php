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

    public function test_notifications_page_paginates_notifications_at_five_per_page(): void
    {
        $user = User::factory()->create();
        for ($index = 1; $index <= 21; $index++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'test.notification',
                'data' => ['title' => 'Notification '.$index],
                'created_at' => now()->addSeconds($index),
                'updated_at' => now()->addSeconds($index),
            ]);
        }

        $firstPage = $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notification 21')
            ->assertSee('aria-label="Pagination Navigation"', false)
            ->viewData('notifications');
        $this->assertSame(5, $firstPage->count());
        $this->assertSame(21, $firstPage->total());

        $secondPage = $this->actingAs($user)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Notification 16')
            ->viewData('notifications');
        $this->assertSame(5, $secondPage->count());
    }
}
