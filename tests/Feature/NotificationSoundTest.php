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
}
