# Laravel Messaging Reactions

Per-message reactions for [phunky/laravel-messaging](https://github.com/phunky/laravel-messaging). Each conversation participant can add one reaction per message. Values are plain strings — emoji, icon slugs (e.g. for Flux `<flux:icon>`), or any convention your UI uses.

## Installation

```bash
composer require phunky/laravel-messaging-reactions
```

Register the extension in `config/messaging.php` (add to the existing `extensions` array — do not remove the core keys):

```php
'extensions' => [
    // ... other MessagingExtension classes, if any
    \Phunky\LaravelMessagingReactions\ReactionsExtension::class,
],
```

```bash
php artisan migrate
```

## Usage

Resolve `ReactionService` via the container (constructor injection, `app(ReactionService::class)`, etc.):

```php
use Phunky\LaravelMessagingReactions\ReactionService;

$reactionService = app(ReactionService::class);
```

### Reacting to a message

```php
// Add or change a reaction — returns the Reaction model
$reaction = $reactionService->react($message, $user, '👍');

// Same value again removes the reaction (toggle) — returns null
$reaction = $reactionService->react($message, $user, '👍');

// A different value replaces the existing reaction
$reaction = $reactionService->react($message, $user, '❤️');
```

Each participant has at most one reaction per message (one row per participant, updated in place). Only conversation participants may react — others throw an exception.

### Removing a reaction

```php
// Removes the participant’s current reaction if any; no-op if none
$reactionService->removeReaction($message, $user);
```

### Fetching reactions

```php
// Reaction models with participant and messageable eager-loaded
$reactions = $reactionService->getReactions($message);

// Grouped summary for counts in the UI
$summary = $reactionService->getReactionSummary($message);

// Each item: ['reaction' => '👍', 'count' => 3, 'participant_ids' => [1, 4, 7]]
```

### `Message` relationship

`Message::reactions()` is registered as a `hasMany` macro — call it as a method:

```php
$message->reactions()->get();
$message->reactions()->count();
```

## Events

`ReactionAdded` and `ReactionRemoved` extend `[BroadcastableMessagingEvent](https://github.com/phunky/laravel-messaging)` from the core package. They implement `ShouldBroadcast` and `ShouldDispatchAfterCommit`, respect `config('messaging.broadcasting.enabled')`, and use the same private conversation channels as core messaging. With Laravel Echo, listen using the `broadcastAs()` name with a **leading dot** (e.g. `.listen('.messaging.reaction.added', …)`).


| Event             | Public properties                                | `broadcastAs()`              |
| ----------------- | ------------------------------------------------ | ---------------------------- |
| `ReactionAdded`   | `$reaction`, `$message`, `$messageable`          | `messaging.reaction.added`   |
| `ReactionRemoved` | `$message`, `$messageable`, `$reaction` (string) | `messaging.reaction.removed` |


`ReactionAdded` includes the full `Reaction` model (`participant` is loaded before dispatch). `ReactionRemoved` passes the reaction string because the row is already gone.

```php
use Illuminate\Support\Facades\Event;
use Phunky\LaravelMessagingReactions\Events\ReactionAdded;

Event::listen(ReactionAdded::class, function (ReactionAdded $event) {
    $event->message->sender?->notify(new \App\Notifications\MessageReacted($event->reaction));
});
```

## License

MIT - see [LICENSE.md](LICENSE.md).