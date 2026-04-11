<?php

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Phunky\LaravelMessagingReactions\Events\ReactionAdded;
use Phunky\LaravelMessagingReactions\Events\ReactionRemoved;
use Phunky\LaravelMessagingReactions\Exceptions\ReactionException;
use Phunky\LaravelMessagingReactions\Reaction;
use Phunky\LaravelMessagingReactions\ReactionService;
use Phunky\LaravelMessagingReactions\Tests\Fixtures\User;
use Phunky\LaravelMessaging\Exceptions\CannotMessageException;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;

function reactionUsers(): array
{
    return [
        User::create(['name' => 'Alice']),
        User::create(['name' => 'Bob']),
    ];
}

describe('react', function () {
    it('adds a reaction for a participant', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $row = $reactions->react($message, $b, '👍');

        expect($row)->toBeInstanceOf(Reaction::class)
            ->and($row->reaction)->toBe('👍')
            ->and($row->message_id)->toBe((int) $message->getKey());

        expect($reactions->getReactions($message))->toHaveCount(1);
    });

    it('overrides an existing reaction for the same participant', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $reactions->react($message, $b, '👍');
        $reactions->react($message, $b, '❤️');

        expect(Reaction::query()->where('message_id', $message->getKey())->count())->toBe(1)
            ->and($reactions->getReactions($message)->first()->reaction)->toBe('❤️');
    });

    it('rejects empty reaction strings', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);

        expect(fn () => $reactions->react($message, $b, '   '))
            ->toThrow(ReactionException::class, 'empty');
    });

    it('dispatches ReactionAdded', function () {
        Event::fake([ReactionAdded::class]);

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(ReactionService::class)->react($message, $b, '🔥');

        Event::assertDispatched(ReactionAdded::class);
    });

    it('allows flux-style icon names as reaction values', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $row = app(ReactionService::class)->react($message, $b, 'hand-thumb-up');

        expect($row->reaction)->toBe('hand-thumb-up');
    });

    it('removes the reaction when applying the same value again', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $reactions->react($message, $b, '👍');
        $out = $reactions->react($message, $b, '👍');

        expect($out)->toBeNull()
            ->and(Reaction::query()->where('message_id', $message->getKey())->count())->toBe(0);
    });

    it('replaces when applying a different reaction', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $reactions->react($message, $b, '👍');
        $out = $reactions->react($message, $b, '❤️');

        expect($out)->not->toBeNull()
            ->and($out->reaction)->toBe('❤️')
            ->and(Reaction::query()->where('message_id', $message->getKey())->count())->toBe(1);
    });
});

describe('removeReaction', function () {
    it('removes the participant reaction and dispatches ReactionRemoved', function () {
        Event::fake([ReactionRemoved::class]);

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $reactions->react($message, $b, '👍');
        $reactions->removeReaction($message, $b);

        expect(Reaction::query()->where('message_id', $message->getKey())->count())->toBe(0);
        Event::assertDispatched(ReactionRemoved::class);
    });

    it('is a no-op when no reaction exists', function () {
        Event::fake([ReactionRemoved::class]);

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(ReactionService::class)->removeReaction($message, $b);

        Event::assertNotDispatched(ReactionRemoved::class);
    });
});

describe('getReactionSummary', function () {
    it('aggregates counts and participant ids', function () {
        $a = User::create(['name' => 'Alice']);
        $b = User::create(['name' => 'Bob']);
        $c = User::create(['name' => 'Carol']);
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b, $c);

        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $reactions->react($message, $b, '👍');
        $reactions->react($message, $c, '👍');
        $reactions->react($message, $a, '❤️');

        $summary = $reactions->getReactionSummary($message);
        $thumbs = $summary->firstWhere('reaction', '👍');

        expect($summary)->toHaveCount(2)
            ->and($thumbs['count'])->toBe(2)
            ->and($thumbs['participant_ids'])->toHaveCount(2);
    });
});

describe('authorization', function () {
    it('blocks non-participants', function () {
        [$a, $b] = reactionUsers();
        $stranger = User::create(['name' => 'Zed']);
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        expect(fn () => app(ReactionService::class)->react($message, $stranger, '👍'))
            ->toThrow(CannotMessageException::class);
    });
});

describe('message delete', function () {
    it('removes reactions when the message is soft-deleted', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        $messageId = (int) $message->getKey();

        app(ReactionService::class)->react($message, $b, '👍');

        expect(Reaction::query()->where('message_id', $messageId)->count())->toBe(1);

        $message->delete();

        expect(Reaction::query()->where('message_id', $messageId)->count())->toBe(0);
    });

    it('removes reactions when the message is force-deleted', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        $messageId = (int) $message->getKey();

        app(ReactionService::class)->react($message, $b, '👍');

        expect(Reaction::query()->where('message_id', $messageId)->count())->toBe(1);

        $message->forceDelete();

        expect(Reaction::query()->where('message_id', $messageId)->count())->toBe(0);
    });
});

describe('broadcasting', function () {
    it('exposes ReactionAdded on the conversation channel when broadcasting is enabled', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $reactions = app(ReactionService::class);
        $row = $reactions->react($message, $b, '👍');

        $event = new ReactionAdded($row, $message, $b);

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
            ->and($event->broadcastWhen())->toBeTrue()
            ->and($event->broadcastOn()[0]->name)->toBe('private-messaging.conversation.'.$conversation->getKey())
            ->and($event->broadcastAs())->toBe('messaging.reaction.added');
    });

    it('skips broadcasting ReactionAdded when broadcasting is disabled', function () {
        Config::set('messaging.broadcasting.enabled', false);

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        $row = app(ReactionService::class)->react($message, $b, '👍');

        $event = new ReactionAdded($row, $message, $b);

        expect($event->broadcastWhen())->toBeFalse();
    });

    it('exposes ReactionRemoved on the conversation channel when broadcasting is enabled', function () {
        Config::set('messaging.broadcasting.enabled', true);
        Config::set('messaging.broadcasting.channel_prefix', 'messaging');

        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');

        app(ReactionService::class)->react($message, $b, '👍');

        $event = new ReactionRemoved($message, $b, '👍');

        expect($event)->toBeInstanceOf(ShouldBroadcast::class)
            ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
            ->and($event->broadcastWhen())->toBeTrue()
            ->and($event->broadcastOn()[0]->name)->toBe('private-messaging.conversation.'.$conversation->getKey())
            ->and($event->broadcastAs())->toBe('messaging.reaction.removed');
    });
});

describe('Message macro', function () {
    it('exposes reactions relationship', function () {
        [$a, $b] = reactionUsers();
        $messaging = app(MessagingService::class);
        [$conversation] = $messaging->findOrCreateConversation($a, $b);
        $message = $messaging->sendMessage($conversation, $a, 'hi');
        app(ReactionService::class)->react($message, $b, '👍');

        $message->refresh();

        expect($message->reactions())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->and($message->reactions()->count())->toBe(1);
    });
});
