<?php

namespace App\Support\Auth;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Jenssegers\Agent\Agent;

/**
 * Reads + revokes rows from the Laravel `sessions` table.
 *
 * Requires SESSION_DRIVER=database. Parses each session row's user_agent into
 * a friendly device label, and exposes revoke-single + revoke-all-but-current.
 */
class SessionManager
{
    /**
     * @return array<int, array{
     *     id: string,
     *     ip_address: ?string,
     *     device: string,
     *     platform: ?string,
     *     browser: ?string,
     *     last_active: string,
     *     is_current: bool,
     * }>
     */
    public function listFor(User $user, ?string $currentSessionId): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($row) use ($currentSessionId) {
                $parsed = $this->parseUserAgent((string) $row->user_agent);

                return [
                    'id' => (string) $row->id,
                    'ip_address' => $row->ip_address,
                    'device' => $parsed['device'],
                    'platform' => $parsed['platform'],
                    'browser' => $parsed['browser'],
                    'last_active' => CarbonImmutable::createFromTimestamp((int) $row->last_activity)->toIso8601String(),
                    'is_current' => $row->id === $currentSessionId,
                ];
            })
            ->all();
    }

    public function revoke(User $user, string $sessionId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete();
    }

    /**
     * Revoke all sessions for the user except (optionally) the current one.
     */
    public function revokeAllExcept(User $user, ?string $exceptSessionId): int
    {
        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($exceptSessionId !== null) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    /**
     * @return array{device: string, platform: ?string, browser: ?string}
     */
    protected function parseUserAgent(string $userAgent): array
    {
        if ($userAgent === '') {
            return ['device' => 'Unknown device', 'platform' => null, 'browser' => null];
        }

        if (class_exists(Agent::class)) {
            $agent = new Agent;
            $agent->setUserAgent($userAgent);

            $platform = $agent->platform() ?: null;
            $browser = $agent->browser() ?: null;
            $device = $agent->device() ?: ($agent->isDesktop() ? 'Desktop' : ($agent->isMobile() ? 'Mobile' : 'Unknown device'));

            return ['device' => (string) $device, 'platform' => $platform, 'browser' => $browser];
        }

        // Minimal fallback parser — covers the common-case strings.
        $platform = null;
        if (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad/i', $userAgent)) {
            $platform = 'iOS';
        }

        $browser = null;
        if (preg_match('/Edg\//i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        }

        return [
            'device' => $platform !== null ? "{$platform} device" : 'Unknown device',
            'platform' => $platform,
            'browser' => $browser,
        ];
    }
}
