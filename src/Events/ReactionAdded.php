<?php

namespace Phunky\LaravelMessagingReactions\Events;

use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Events\BroadcastableMessagingEvent;
use Phunky\LaravelMessaging\Models\Message;

class ReactionAdded extends BroadcastableMessagingEvent
{
    public function __construct(
        public Reaction $reaction,
        public Message $message,
        public Messageable $messageable,
    ) {
        parent::__construct($message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'messaging.reaction.added';
    }
}
