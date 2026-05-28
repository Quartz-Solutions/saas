<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\V1\Concerns\ApiController;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Per-user channel × event preference matrix.
 *
 * @group Notifications
 *
 * @authenticated
 */
class NotificationsPreferencesController extends ApiController
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * GET /notification-preferences. Ability: `notifications:read`.
     */
    public function show(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'notifications:read');

        $user = $this->actor($request);

        return response()->json([
            'data' => [
                'events' => array_map(
                    static fn (string $slug, array $def) => [
                        'slug' => $slug,
                        'label' => $def['label'] ?? $slug,
                        'description' => $def['description'] ?? null,
                        'group' => $def['group'] ?? 'general',
                        'always_on' => (bool) ($def['always_on'] ?? false),
                    ],
                    array_keys($this->dispatcher->events()),
                    array_values($this->dispatcher->events()),
                ),
                'channels' => array_map(
                    static fn (string $slug, array $def) => [
                        'slug' => $slug,
                        'label' => $def['label'] ?? $slug,
                        'description' => $def['description'] ?? null,
                    ],
                    array_keys($this->dispatcher->channels()),
                    array_values($this->dispatcher->channels()),
                ),
                'preferences' => $this->dispatcher->preferencesFor($user),
            ],
        ]);
    }

    /**
     * PATCH /notification-preferences. Body accepts partial updates as
     * a list of {event, channel, enabled} triples. Ability: `notifications:write`.
     *
     * Example body:
     *   {
     *     "preferences": [
     *       { "event": "tenant_invite", "channel": "email", "enabled": false }
     *     ]
     *   }
     */
    public function update(Request $request): JsonResponse
    {
        $this->requireAbility($request, 'notifications:write');

        $user = $this->actor($request);
        $events = $this->dispatcher->events();
        $channels = $this->dispatcher->channels();

        $data = Validator::make($request->all(), [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.event' => ['required', 'string'],
            'preferences.*.channel' => ['required', 'string'],
            'preferences.*.enabled' => ['required', 'boolean'],
        ])->validate();

        $applied = 0;
        $skipped = [];

        foreach ($data['preferences'] as $row) {
            $event = (string) $row['event'];
            $channel = (string) $row['channel'];

            if (! isset($events[$event]) || ! isset($channels[$channel])) {
                $skipped[] = compact('event', 'channel');

                continue;
            }

            if (! empty($events[$event]['always_on'])) {
                $skipped[] = ['event' => $event, 'channel' => $channel, 'reason' => 'always_on'];

                continue;
            }

            $this->dispatcher->setPreference($user, $event, $channel, (bool) $row['enabled']);
            $applied++;
        }

        return response()->json([
            'data' => [
                'applied_count' => $applied,
                'skipped' => $skipped,
                'preferences' => $this->dispatcher->preferencesFor($user),
            ],
        ]);
    }
}
