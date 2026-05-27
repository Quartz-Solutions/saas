import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Session = {
    public_id: string;
    status: string;
    tenant: { id: number; slug: string; name: string } | null;
};

type Props = {
    session: Session;
    pollUrl: string;
};

export default function CheckoutProcessing({ session, pollUrl }: Props) {
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        let timer: ReturnType<typeof setInterval>;
        let secondsTimer: ReturnType<typeof setInterval>;

        const poll = async () => {
            try {
                const { data } = await axios.get<{
                    status: string;
                    tenant_slug: string | null;
                }>(pollUrl);

                if (data.status === 'completed' && data.tenant_slug) {
                    clearInterval(timer);
                    clearInterval(secondsTimer);
                    router.visit(`/t/${data.tenant_slug}/dashboard`);
                }
            } catch {
                // swallow — keep polling
            }
        };

        timer = setInterval(poll, 3000);
        secondsTimer = setInterval(() => setElapsed((e) => e + 1), 1000);
        poll(); // initial check

        return () => {
            clearInterval(timer);
            clearInterval(secondsTimer);
        };
    }, [pollUrl]);

    const slow = elapsed > 30;

    return (
        <>
            <Head title="Finishing up…" />
            <div className="mx-auto max-w-xl px-4 py-16">
                <Card>
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-3">
                            {slow ? (
                                <CheckCircle2 className="size-12 text-amber-500" />
                            ) : (
                                <Loader2 className="size-12 animate-spin text-primary" />
                            )}
                        </div>
                        <CardTitle>
                            {slow ? 'Still working on it…' : 'Activating your subscription'}
                        </CardTitle>
                        <CardDescription>
                            {slow
                                ? "This is taking longer than usual. We'll keep checking — feel free to refresh after a minute."
                                : 'Your payment was successful. Waiting for the gateway to confirm.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-center text-sm text-muted-foreground">
                        Elapsed: {elapsed}s
                    </CardContent>
                </Card>
                <p className="mt-6 text-center text-xs text-muted-foreground">
                    Session: <code className="font-mono">{session.public_id}</code>
                </p>
            </div>
        </>
    );
}
