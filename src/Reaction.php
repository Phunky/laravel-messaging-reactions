<?php

namespace Phunky\LaravelMessagingReactions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Models\Participant;

/**
 * @property int $id
 * @property int $message_id
 * @property int $participant_id
 * @property string $reaction
 * @property-read Message $message
 * @property-read Participant $participant
 */
class Reaction extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'participant_id',
        'reaction',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = messaging_table('reactions');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.message'), 'message_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(config('messaging.models.participant'), 'participant_id');
    }
}
