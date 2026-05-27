import { router } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Session = {
    public_id: string;
    gateway: string | null;
    result_kind: string | null;
    result_payload: Record<string, unknown> | null;
};

type Props = { session: Session };

/**
 * Renders the gateway-specific next step once the server has called the
 * driver's initiateCheckout and stored a `result_kind` + `result_payload`
 * on the CheckoutSession.
 *
 * For Phase 2 the only kind exercised by Stripe is `redirect`. The other
 * kinds (form_post, iframe, widget, kiosk_ref) are scaffolded with TODO
 * placeholders — Phase 4 fills them in as each non-Stripe driver lands.
 */
export default function CheckoutNextStep({ session }: Props) {
    const payload = session.result_payload ?? {};

    useEffect(() => {
        if (session.result_kind === 'redirect' && typeof payload.url === 'string') {
            // Hard-navigate; this leaves the Inertia SPA.
            window.location.href = payload.url;
        }
    }, [session.result_kind, payload]);

    if (session.result_kind === 'redirect') {
        return (
            <CenteredCard
                title="Redirecting to the gateway"
                description={`Taking you to ${session.gateway ?? 'the payment provider'}…`}
            />
        );
    }

    if (session.result_kind === 'form_post') {
        const action = (payload.action as string | undefined) ?? '';
        const params = (payload.params as Record<string, string | number> | undefined) ?? {};

        return (
            <form
                method={(payload.method as string | undefined) ?? 'POST'}
                action={action}
                id="gateway-form"
                ref={(el) => el?.submit()}
            >
                {Object.entries(params).map(([k, v]) => (
                    <input key={k} type="hidden" name={k} value={String(v)} />
                ))}
            </form>
        );
    }

    if (session.result_kind === 'iframe') {
        const src = (payload.iframe_url as string | undefined) ?? '';
        const attrs = (payload.iframe_attributes as Record<string, string> | undefined) ?? {};

        return (
            <div className="mx-auto max-w-2xl px-4 py-6">
                <iframe
                    src={src}
                    title="Payment"
                    className="w-full rounded-lg border bg-background"
                    style={{ height: attrs.height ?? '700px' }}
                    allow="payment *"
                />
                <p className="mt-2 text-center text-xs text-muted-foreground">
                    Complete the payment in the frame above. We'll redirect you when it's done.
                </p>
            </div>
        );
    }

    if (session.result_kind === 'widget') {
        const scriptUrl = (payload.script_url as string | undefined) ?? '';
        const widgetConfig = (payload.widget_config as Record<string, unknown> | undefined) ?? {};
        // Load the gateway's payment widget script and let it render into a container.
        useEffect(() => {
            const tag = document.createElement('script');
            tag.src = scriptUrl;
            tag.async = true;
            document.body.appendChild(tag);

            return () => {
                tag.remove();
            };
        }, [scriptUrl]);

        return (
            <div className="mx-auto max-w-md px-4 py-6">
                <form
                    className="paymentWidgets"
                    data-brands={(widgetConfig.brands as string) ?? 'VISA MASTER'}
                    action={(widgetConfig.shopperResultUrl as string) ?? ''}
                />
            </div>
        );
    }

    if (session.result_kind === 'kiosk_ref') {
        const reference = (payload.reference as string | undefined) ?? '';
        const instructionsUrl = payload.instructions_url as string | undefined;

        return (
            <div className="mx-auto max-w-md px-4 py-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Pay at a kiosk</CardTitle>
                        <CardDescription>
                            Show this reference at any participating outlet.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="rounded-lg bg-muted/40 p-4 text-center">
                            <div className="text-xs uppercase tracking-wider text-muted-foreground">
                                Reference
                            </div>
                            <div className="mt-1 font-mono text-2xl font-semibold">
                                {reference}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => navigator.clipboard?.writeText(reference)}
                            className="w-full rounded-md border px-3 py-2 text-sm hover:bg-muted/50"
                        >
                            Copy code
                        </button>
                        {instructionsUrl ? (
                            <a
                                href={instructionsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block text-center text-sm underline"
                            >
                                How does this work?
                            </a>
                        ) : null}
                        <p className="text-center text-xs text-muted-foreground">
                            Once you pay, we'll activate your subscription automatically.
                            You can close this page.
                        </p>
                    </CardContent>
                </Card>
            </div>
        );
    }

    return (
        <CenteredCard
            title="Working on it"
            description="Something went off-script. Try refreshing in a moment."
        />
    );
}

function CenteredCard({ title, description }: { title: string; description: string }) {
    return (
        <div className="mx-auto max-w-md px-4 py-16">
            <Card>
                <CardHeader className="text-center">
                    <div className="mx-auto mb-3">
                        <Loader2 className="size-10 animate-spin text-primary" />
                    </div>
                    <CardTitle>{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent className="text-center" />
            </Card>
        </div>
    );
}
