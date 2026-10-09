<?php

namespace App\Events;

use App\Models\Chat\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'conversation.' . $this->message->id_conversation
            ),
        ];
    }

    public function broadcastAs(): string {
        return 'message.sent';
    }

    public function broadcastWith(): array {
        return [
            'message' => [
                'id_message' => $this->message->id_message,
                'id_conversation' => $this->message->id_conversation,
                'sender_id' => $this->message->sender_id,
                'type_message' => $this->message->type_message,
                'message' => $this->message->message,
                'attachment' => $this->message->attachment,
                'attachment_url' => $this->message->attachment_url,
                'sender' => $this->message->sender,
                'sent_at' => $this->message->sent_at,
                'edited_at' => $this->message->edited_at,
            ]
        ];
    }
}
