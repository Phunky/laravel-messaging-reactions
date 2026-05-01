<?php

namespace Phunky\LaravelMessagingReactions;

use Illuminate\Support\Collection;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Models\Conversation;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;
use Phunky\LaravelMessagingReactions\Events\ReactionAdded;
use Phunky\LaravelMessagingReactions\Events\ReactionRemoved;
use Phunky\LaravelMessagingReactions\Exceptions\ReactionException;

class ReactionService
{
    public function __construct(
        protected MessagingService $messaging,
    ) {}

    /**
     * Add or change the participant's reaction. Calling the same reaction again removes it (toggle off).
     */
    public function react(Message $message, Messageable $messageable, string $reaction): ?Reaction
    {
        $reaction = trim($reaction);
        if ($reaction === '') {
            throw new ReactionException('Reaction cannot be empty.');
        }

        /** @var Conversation $conversation */
        $conversation = $message->conversation()->firstOrFail();
        $participant = $this->messaging->findParticipantOrFail($conversation, $messageable);

        /** @var Reaction|null $existing */
        $existing = Reaction::query()
            ->where('message_id', $message->getKey())
            ->where('participant_id', $participant->getKey())
            ->first();

        if ($existing && $existing->reaction === $reaction) {
            $value = $existing->reaction;
            $existing->delete();
            $this->touchConversationActivity($conversation, now(), 'reaction.removed');

            event(new ReactionRemoved($message, $messageable, $value));

            return null;
        }

        /** @var Reaction $model */
        $model = Reaction::query()->updateOrCreate(
            [
                'message_id' => $message->getKey(),
                'participant_id' => $participant->getKey(),
            ],
            ['reaction' => $reaction],
        );

        $model->load('participant');

        $this->touchConversationActivity($conversation, now(), 'reaction.updated');

        event(new ReactionAdded($model, $message, $messageable));

        return $model;
    }

    public function removeReaction(Message $message, Messageable $messageable): void
    {
        /** @var Conversation $conversation */
        $conversation = $message->conversation()->firstOrFail();
        $participant = $this->messaging->findParticipantOrFail($conversation, $messageable);

        /** @var Reaction|null $row */
        $row = Reaction::query()
            ->where('message_id', $message->getKey())
            ->where('participant_id', $participant->getKey())
            ->first();

        if (! $row) {
            return;
        }

        $value = $row->reaction;
        $row->delete();
        $this->touchConversationActivity($conversation, now(), 'reaction.removed');

        event(new ReactionRemoved($message, $messageable, $value));
    }

    protected function touchConversationActivity(Conversation $conversation, mixed $activityAt, string $activityType): void
    {
        $this->messaging->touchConversationActivity($conversation, $activityAt, $activityType);
    }

    /**
     * @return Collection<int, Reaction>
     */
    public function getReactions(Message $message): Collection
    {
        return Reaction::query()
            ->where('message_id', $message->getKey())
            ->with(['participant.messageable'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array{reaction: string, count: int, participant_ids: list<int|string>}>
     */
    public function getReactionSummary(Message $message): Collection
    {
        return Reaction::query()
            ->where('message_id', $message->getKey())
            ->orderBy('id')
            ->get()
            ->groupBy('reaction')
            ->map(static function (Collection $group, string $reaction): array {
                return [
                    'reaction' => $reaction,
                    'count' => $group->count(),
                    'participant_ids' => $group->pluck('participant_id')->values()->all(),
                ];
            })
            ->values();
    }
}
