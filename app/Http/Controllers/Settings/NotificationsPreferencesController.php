<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferencesUpdateRequest;
use App\Support\Notifications\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsPreferencesController extends Controller
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /**
     * Show the channel × event matrix.
     */
    public function edit(): Response
    {
        $user = request()->user();

        return Inertia::render('settings/notifications', [
            'events' => collect($this->dispatcher->events())
                ->map(fn ($definition, $slug) => [
                    'slug' => $slug,
                    'label' => $definition['label'] ?? $slug,
                    'description' => $definition['description'] ?? null,
                    'group' => $definition['group'] ?? 'general',
                    'always_on' => (bool) ($definition['always_on'] ?? false),
                ])
                ->values()
                ->all(),
            'channels' => collect($this->dispatcher->channels())
                ->map(fn ($definition, $slug) => [
                    'slug' => $slug,
                    'label' => $definition['label'] ?? $slug,
                    'description' => $definition['description'] ?? null,
                ])
                ->values()
                ->all(),
            'preferences' => $this->dispatcher->preferencesFor($user),
        ]);
    }

    /**
     * Persist the matrix. Only known event/channel pairs are written;
     * unknown keys are ignored so the registry remains the single source of
     * truth.
     */
    public function update(NotificationPreferencesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $events = $this->dispatcher->events();
        $channels = $this->dispatcher->channels();

        foreach ((array) $request->input('preferences', []) as $event => $payload) {
            if (! is_array($payload) || ! isset($events[$event])) {
                continue;
            }

            // Always-on events ignore preferences entirely — silently skip
            // any attempt to flip them off so the UI can disable the toggle
            // without us writing a misleading row.
            if (! empty($events[$event]['always_on'])) {
                continue;
            }

            foreach ($payload as $channel => $enabled) {
                if (! isset($channels[$channel])) {
                    continue;
                }

                $this->dispatcher->setPreference($user, $event, $channel, (bool) $enabled);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Notification preferences updated.'),
        ]);

        return back();
    }
}
