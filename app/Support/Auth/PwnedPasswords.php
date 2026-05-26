<?php

namespace App\Support\Auth;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HaveIBeenPwned Pwned-Passwords k-anonymity lookup.
 *
 * Sends only the first 5 characters of the SHA-1 of the password,
 * receives back the matching suffix list, then checks locally for a
 * match. The cleartext password never leaves this process.
 *
 * https://haveibeenpwned.com/API/v3#PwnedPasswords
 *
 * Negative answers (password not compromised) are cached for 24 hours
 * to limit outbound traffic. Positive answers are not cached — a user
 * may rotate to a password that later appears in a breach corpus.
 */
class PwnedPasswords
{
    public const API_BASE = 'https://api.pwnedpasswords.com/range/';

    public const CACHE_PREFIX = 'pwned-passwords:negative:';

    public const CACHE_TTL_SECONDS = 86400; // 24h

    public const REQUEST_TIMEOUT_SECONDS = 3;

    /**
     * Returns true when the password appears in a known breach corpus.
     * On any network/parsing error, returns false (fail-open) and logs.
     */
    public function isCompromised(string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        if (Cache::has(self::CACHE_PREFIX.$prefix.':'.$suffix)) {
            return false;
        }

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withHeaders(['Add-Padding' => 'true'])
                ->get(self::API_BASE.$prefix);
        } catch (ConnectionException $e) {
            Log::warning('PwnedPasswords lookup failed (connection)', ['error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('PwnedPasswords lookup returned non-2xx', ['status' => $response->status()]);

            return false;
        }

        $body = $response->body();

        foreach (preg_split("/\r?\n/", $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Format: SUFFIX:COUNT
            [$lineSuffix, $count] = array_pad(explode(':', $line, 2), 2, '0');

            if (strcasecmp($lineSuffix, $suffix) === 0 && (int) $count > 0) {
                return true;
            }
        }

        Cache::put(
            self::CACHE_PREFIX.$prefix.':'.$suffix,
            true,
            self::CACHE_TTL_SECONDS,
        );

        return false;
    }
}
