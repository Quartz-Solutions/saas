<?php

namespace App\Support\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Canonical service for every outbound notification.
 *
 * Per CLAUDE.md "service-layer single seam": ALL email/database notification
 * dispatching goes through this class. Direct `Mail::send(...)` /
 * `$user->notify(...)` calls outside this service are bugs.
 *
 * The event slug ↔ channel matrix lives in `config/notifications.php` and is
 * overridden per-user by rows in the `notification_preferences` table.
 *
 *   - "Always-on" events (verification, password reset, magic link) ignore
 *     user preferences and always deliver via email.
 *   - Other events deliver via every channel whose preference is enabled
 *     (default channels come from `events.<slug>.defaults`).
 *   - If a user has disabled every channel for a non-always-on event the
 *     dispatcher skips entirely.
 */
class NotificationDispatcher
{
    /**
     * Dispatch a notification event for a user.
     *
     * @param  array<string, mixed>  $data  Arbitrary payload forwarded to the
     *                                      Mailable constructor as `$context`
     *                                      and stored on database notifications.
     * @return array<string, bool> Map of channel → dispatched flag.
     */
    public function send(User $user, string $event, array $data = []): array
    {
        $definition = $this->definition($event);

        $channels = $this->resolveChannels($user, $event, $definition);

        $dispatched = [];

        foreach ($channels as $channel) {
            $dispatched[$channel] = match ($channel) {
                'email' => $this->sendEmail($user, $event, $definition, $data),
                'database' => $this->sendDatabase($user, $event, $definition, $data),
                default => false,
            };
        }

        return $dispatched;
    }

    /**
     * Return the resolved channel list for a (user, event) tuple. Useful for
     * tests + the preferences UI.
     *
     * @return list<string>
     */
    public function channelsFor(User $user, string $event): array
    {
        return $this->resolveChannels($user, $event, $this->definition($event));
    }

    /**
     * The canonical event registry as defined in config/notifications.php.
     *
     * @return array<string, array<string, mixed>>
     */
    public function events(): array
    {
        return config('notifications.events', []);
    }

    /**
     * The canonical channel registry. Only channels marked `enabled` here can
     * fire — Slack/SMS are deferred.
     *
     * @return array<string, array<string, mixed>>
     */
    public function channels(): array
    {
        return collect(config('notifications.channels', []))
            ->filter(fn ($channel) => (bool) ($channel['enabled'] ?? false))
            ->all();
    }

    /**
     * Compute the per-channel preference matrix for a user, falling back to
     * config defaults when a row is missing. Used by the settings page.
     *
     * @return array<string, array<string, bool>>
     */
    public function preferencesFor(User $user): array
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($row) => $row->event_type.'|'.$row->channel);

        $matrix = [];

        foreach ($this->events() as $slug => $definition) {
            foreach (array_keys($this->channels()) as $channel) {
                $key = $slug.'|'.$channel;
                if ($stored->has($key)) {
                    $matrix[$slug][$channel] = (bool) $stored->get($key)->enabled;
                } else {
                    $matrix[$slug][$channel] = (bool) ($definition['defaults'][$channel] ?? false);
                }
            }
        }

        return $matrix;
    }

    /**
     * Persist a single (user, event, channel, enabled) preference.
     */
    public function setPreference(User $user, string $event, string $channel, bool $enabled): NotificationPreference
    {
        if (! isset($this->events()[$event])) {
            throw new InvalidArgumentException("Unknown notification event: {$event}");
        }

        if (! isset($this->channels()[$channel])) {
            throw new InvalidArgumentException("Unknown or disabled notification channel: {$channel}");
        }

        return NotificationPreference::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'event_type' => $event,
                'channel' => $channel,
            ],
            [
                'enabled' => $enabled,
            ],
        );
    }

    /**
     * Resolve which channels should fire for an event + user.
     *
     * @param  array<string, mixed>  $definition
     * @return list<string>
     */
    private function resolveChannels(User $user, string $event, array $definition): array
    {
        $availableChannels = array_keys($this->channels());

        // Always-on events bypass preferences and email always.
        if ($definition['always_on'] ?? false) {
            return array_values(array_filter(
                $availableChannels,
                fn ($c) => (bool) ($definition['defaults'][$c] ?? false),
            ));
        }

        $preferences = $this->preferencesFor($user)[$event] ?? [];

        return array_values(array_filter(
            $availableChannels,
            fn ($c) => (bool) ($preferences[$c] ?? false),
        ));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    private function sendEmail(User $user, string $event, array $definition, array $data): bool
    {
        $mailableClass = $definition['mailable'] ?? null;

        if (! $mailableClass || ! class_exists($mailableClass)) {
            return false;
        }

        $mailable = $this->buildMailable($mailableClass, $user, $event, $data);

        Mail::to($user->email)->queue($mailable);

        return true;
    }

    /**
     * Build a Mailable. We resolve the constructor signature heuristically so
     * the dispatcher works for both the User-only mailables (welcome) and the
     * (User, array $context) mailables (login-alert, payment-receipt, …).
     *
     * @param  class-string  $class
     * @param  array<string, mixed>  $data
     */
    private function buildMailable(string $class, User $user, string $event, array $data): mixed
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $params = $constructor->getParameters();

        // Single-arg User constructor.
        if (count($params) === 1 && $this->paramAccepts($params[0], User::class)) {
            return $reflection->newInstance($user);
        }

        // (User, array) constructor — most common.
        if (count($params) >= 2 && $this->paramAccepts($params[0], User::class)) {
            // If the second param is a string and a matching key is in $data,
            // pass it positionally (e.g. EmailVerificationMail::$verifyUrl).
            if ($params[1]->getType() instanceof \ReflectionNamedType
                && $params[1]->getType()->getName() === 'string'
            ) {
                $key = $params[1]->getName();
                $value = $data[$key] ?? '';

                return $reflection->newInstance($user, (string) $value);
            }

            return $reflection->newInstance($user, $data);
        }

        // Fallback — passthrough.
        return $reflection->newInstance(...array_slice([$user, $data], 0, count($params)));
    }

    private function paramAccepts(\ReflectionParameter $param, string $class): bool
    {
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName() === $class || is_subclass_of($class, $type->getName());
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    private function sendDatabase(User $user, string $event, array $definition, array $data): bool
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\'.Str::studly($event),
            'data' => array_merge([
                'event' => $event,
                'title' => $definition['label'] ?? Str::headline($event),
                'description' => $definition['description'] ?? null,
            ], $data),
        ]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $event): array
    {
        $events = $this->events();

        if (! isset($events[$event])) {
            throw new InvalidArgumentException("Unknown notification event: {$event}");
        }

        return $events[$event];
    }
}
