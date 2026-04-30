<?php

namespace Phunky\LaravelMessagingReactions\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessagingReactions\Reaction;

class ReactionAdded extends BroadcastableMessagingEvent
{
    public const BROADCAST_NAME = 'messaging.reaction.added';

    public function __construct(
        public Reaction $reaction,
        public Message $message,
        public Messageable $messageable,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_NAME;
    }

    /**
     * @return array{conversation_id: int|string, message_id: int|string, reaction_id: int|string, reaction: string, messageable_type: string, messageable_id: int|string}
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->getAttribute('conversation_id'),
            'message_id' => $this->message->getKey(),
            'reaction_id' => $this->reaction->getKey(),
            'reaction' => $this->reaction->reaction,
            'messageable_type' => $this->messageable->getMorphClass(),
            'messageable_id' => $this->messageable->getKey(),
        ];
    }
}
