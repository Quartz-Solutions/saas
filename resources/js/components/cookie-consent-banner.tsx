import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import marketingRoutes from '@/routes/marketing';
import { store as cookieConsentStore } from '@/routes/marketing/cookie-consent';

const STORAGE_KEY = 'cookie_consent_choice';

type Choice = 'accepted' | 'rejected';

function readStoredChoice(): Choice | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored === 'accepted' || stored === 'rejected') {
            return stored;
        }
    } catch {
        // localStorage may throw in private mode — fall through.
    }

    return null;
}

/**
 * Bottom-fixed cookie consent banner. Persists choice to localStorage
 * for instant-hide UX + POSTs to the server so a cookie is set
 * (Inertia shares it back via HandleInertiaRequests).
 */
export default function CookieConsentBanner({
    initialChoice = null,
    className,
}: {
    initialChoice?: Choice | null;
    className?: string;
}) {
    const [dismissed, setDismissed] = useState<boolean>(
        () => initialChoice !== null || readStoredChoice() !== null,
    );

    if (dismissed) {
        return null;
    }

    const persist = (choice: Choice) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, choice);
        } catch {
            // ignore
        }

        router.post(
            cookieConsentStore().url,
            { choice },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['cookieConsent'],
                onFinish: () => setDismissed(true),
            },
        );
    };

    return (
        <div
            role="region"
            aria-label="Cookie consent"
            className={cn(
                'fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 shadow-lg backdrop-blur',
                className,
            )}
            data-test="cookie-consent-banner"
        >
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-4 md:flex-row md:items-center md:justify-between md:gap-6 md:px-6">
                <div className="text-sm text-muted-foreground">
                    We use cookies to keep you signed in, remember your preferences and
                    measure how the site is used. See our{' '}
                    <Link
                        href={marketingRoutes.legal.show('cookies').url}
                        className="font-medium text-foreground underline underline-offset-4"
                    >
                        Cookie Policy
                    </Link>
                    .
                </div>
                <div className="flex shrink-0 gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => persist('rejected')}
                        data-test="cookie-consent-reject"
                    >
                        Reject
                    </Button>
                    <Button
                        size="sm"
                        onClick={() => persist('accepted')}
                        data-test="cookie-consent-accept"
                    >
                        Accept
                    </Button>
                </div>
            </div>
        </div>
    );
}
