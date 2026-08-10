<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast whenever a consumer sends a message to a pharmacy (or a
 * pharmacy replies), enabling real-time chat notifications.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $consumerId;
    public $pharmacyId;
    public $message;
    public $consumerName;
    public $direction; // 'consumer_to_pharmacy' | 'pharmacy_to_consumer'
    public $reply;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $messageId,
        int $consumerId,
        int $pharmacyId,
        string $message,
        ?string $consumerName = null,
        string $direction = 'consumer_to_pharmacy',
        ?string $reply = null
    ) {
        $this->messageId = $messageId;
        $this->consumerId = $consumerId;
        $this->pharmacyId = $pharmacyId;
        $this->message = $message;
        $this->consumerName = $consumerName;
        $this->direction = $direction;
        $this->reply = $reply;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('pharmacy.' . $this->pharmacyId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
