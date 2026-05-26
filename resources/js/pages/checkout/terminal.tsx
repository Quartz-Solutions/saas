import { Head, Link } from '@inertiajs/react';
import { ArrowRight, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

type Session = {
    public_id: string;
    status: string;
    cancel_reason?: string | null;
    plan: { slug: string; name: string } | null;
    tenant: { slug: string; name: string } | null;
};

type Props = { session: Session };

export default function CheckoutTerminal({ session }: Props) {
    const label =
        session.status === 'canceled'
            ? 'Checkout canceled'
            : session.status === 'expired'
              ? 'Checkout expired'
              : 'Checkout failed';

    return (
        <>
            <Head title={label} />
            <div className="mx-auto max-w-xl px-4 py-16">
                <Card>
                    <CardHeader className="text-center">
                        <div className="mx-auto mb-3">
                            <XCircle className="size-12 text-muted-foreground" />
                        </div>
                        <CardTitle>{label}</CardTitle>
                        <CardDescription>
                            No charge was made. You can start a new checkout whenever you're ready.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-3">
                        {session.tenant ? (
                            <Button asChild>
                                <Link href={`/t/${session.tenant.slug}/billing/plans`}>
                                    <ArrowRight className="size-4" />
                                    Back to plans
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild>
                                <Link href="/pricing">
                                    <ArrowRight className="size-4" />
                                    Pricing
                                </Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
