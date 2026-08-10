<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast whenever a pharmacy's inventory stock changes so that
 * the public map & consumer frontend update in real time.
 */
class InventoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $pharmacyId;
    public $medicineId;
    public $medicineName;
    public $stock;
    public $price;
    public $prescription;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $pharmacyId,
        ?int $medicineId,
        ?string $medicineName,
        int $stock,
        float $price,
        bool $prescription = false
    ) {
        $this->pharmacyId = $pharmacyId;
        $this->medicineId = $medicineId;
        $this->medicineName = $medicineName;
        $this->stock = $stock;
        $this->price = $price;
        $this->prescription = $prescription;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('inventory'),
            new Channel('inventory.' . $this->pharmacyId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'inventory.updated';
    }
}
