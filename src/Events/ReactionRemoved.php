<?php

namespace Phunky\LaravelMessagingReactions\Events;

use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;

class ReactionRemoved extends BroadcastableMessagingEvent
{
    public function __construct(
        public Message $message,
        public Messageable $messageable,
        public string $reaction,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'messaging.reaction.removed';
    }
}
