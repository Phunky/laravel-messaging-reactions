<?php

namespace Phunky\LaravelMessagingReactions;

use Illuminate\Contracts\Foundation\Application;
use Phunky\LaravelMessaging\Contracts\MessagingExtension;
use Phunky\LaravelMessaging\Models\Message;
use Phunky\LaravelMessaging\Services\MessagingService;

class ReactionsExtension implements MessagingExtension
{
    public function register(Application $app): void
    {
        $app->singleton(ReactionService::class, fn (Application $app): ReactionService => new ReactionService(
            $app->make(MessagingService::class),
        ));
    }

    public function boot(Application $app): void
    {
        $migrationDir = dirname(__DIR__).'/database/migrations';

        $app->afterResolving('migrator', function ($migrator) use ($migrationDir): void {
            $migrator->path($migrationDir);
        });

        if (! Message::hasMacro('reactions')) {
            Message::macro('reactions', function () {
                /** @var Message $this */
                return $this->hasMany(Reaction::class, 'message_id');
            });
        }

        Message::deleted(function (Message $message): void {
            Reaction::query()->where('message_id', $message->getKey())->delete();
        });
    }
}
