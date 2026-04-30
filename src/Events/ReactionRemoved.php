<?php

namespace Phunky\LaravelMessagingReactions\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;

class ReactionRemoved extends BroadcastableMessagingEvent
{
    public const BROADCAST_NAME = 'messaging.reaction.removed';

    public function __construct(
        public Message $message,
        public Messageable $messageable,
        public string $reaction,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_NAME;
    }

    /**
     * @return array{conversation_id: int|string, message_id: int|string, reaction: string, messageable_type: string, messageable_id: int|string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->getAttribute('conversation_id'),
            'message_id' => $this->message->getKey(),
            'reaction' => $this->reaction,
            'messageable_type' => $this->messageable->getMorphClass(),
            'messageable_id' => $this->messageable->getKey(),
        ];
    }
}
