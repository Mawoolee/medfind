<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'consumer_id'  => User::factory()->state(['role' => 'consumer']),
            'pharmacy_id'  => Pharmacy::factory(),
            'message'      => fake()->sentence(),
            'is_read'      => false,
            'reply'        => null,
            'replied_at'   => null,
            'prescription_image' => null,
            'verification_status' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(['is_read' => true]);
    }

    public function withReply(): static
    {
        return $this->state([
            'reply'      => fake()->sentence(),
            'replied_at' => now(),
            'is_read'    => true,
        ]);
    }
}
