import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, CreditCard, ExternalLink, Loader2 } from 'lucide-react';
import CheckoutNextStep from '@/components/checkout/checkout-next-step';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type Plan = {
    slug: string;
    name: string;
    description: string | null;
    price_cents: number;
    currency: string;
    billing_period: string;
    trial_days: number;
};

type Tenant = { id: number; slug: string; name: string };

type Session = {
    public_id: string;
    intent: string;
    status: string;
    gateway: string | null;
    currency: string;
    amount_cents: number;
    result_kind: string | null;
    result_payload: Record<string, unknown> | null;
    expires_at: string | null;
    plan: Plan | null;
    tenant: Tenant | null;
};

type Gateway = {
    id: string;
    name: string;
    meta: {
        description?: string;
        regions?: string[];
        capabilities?: string[];
        driver_status?: string;
    };
};

type Props = {
    session: Session;
    gateways: Gateway[];
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

export default function CheckoutShow({ session, gateways }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        gateway: gateways[0]?.id ?? '',
    });

    const pick = (id: string) => setData('gateway', id);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/checkout/${session.public_id}/pay`);
    };

    // After gateway pick, status flips to awaiting_payment and the server
    // populates result_kind + result_payload. Render the next step instead
    // of the picker.
    if (session.status === 'awaiting_payment' && session.result_kind && session.result_payload) {
        return (
            <>
                <Head title="Completing checkout" />
                <CheckoutNextStep session={session} />
            </>
        );
    }

    return (
        <>
            <Head title={`Checkout — ${session.plan?.name ?? 'Plan'}`} />

            <div className="mx-auto max-w-4xl px-4 py-10">
                <div className="mb-6">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={session.tenant ? `/t/${session.tenant.slug}/billing/plans` : '/pricing'}>
                            <ArrowLeft className="size-4" />
                            Back to plans
                        </Link>
                    </Button>
                </div>

                <Heading
                    title="Checkout"
                    description="Pick how you want to pay. We'll redirect you to the gateway's secure page."
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Choose how to pay</CardTitle>
                                <CardDescription>
                                    Each option is processed by the gateway's own hosted page —
                                    we never see your card details.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {gateways.length === 0 ? (
                                    <div className="rounded-lg border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                                        No payment options available for {session.currency}. An admin
                                        can enable a gateway at /admin/gateways.
                                    </div>
                                ) : (
                                    <form onSubmit={submit} className="space-y-3">
                                        {gateways.map((g) => (
                                            <GatewayTile
                                                key={g.id}
                                                gateway={g}
                                                selected={data.gateway === g.id}
                                                onSelect={() => pick(g.id)}
                                            />
                                        ))}
                                        {errors.gateway ? (
                                            <p className="text-sm text-destructive">{errors.gateway}</p>
                                        ) : null}
                                        <Button
                                            type="submit"
                                            disabled={processing || !data.gateway}
                                            className="w-full"
                                            size="lg"
                                        >
                                            {processing ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <ArrowRight className="size-4" />
                                            )}
                                            Continue to {gateways.find((g) => g.id === data.gateway)?.name ?? 'payment'}
                                        </Button>
                                    </form>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div>
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Order summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <div>
                                    <div className="font-medium">{session.plan?.name}</div>
                                    <div className="text-muted-foreground">
                                        {session.plan?.description ?? ' '}
                                    </div>
                                </div>
                                <div className="flex items-baseline justify-between border-t pt-3">
                                    <span className="text-muted-foreground">Today</span>
                                    <span className="font-mono text-lg font-semibold">
                                        {formatMoney(session.amount_cents, session.currency)}
                                    </span>
                                </div>
                                {session.plan?.trial_days ? (
                                    <Badge variant="secondary">
                                        {session.plan.trial_days}-day free trial
                                    </Badge>
                                ) : null}
                                <div className="border-t pt-3 text-xs text-muted-foreground">
                                    Workspace: <span className="font-medium">{session.tenant?.name}</span>
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    Plan billed {session.plan?.billing_period}
                                </div>
                            </CardContent>
                            <CardFooter className="border-t pt-4">
                                <form
                                    method="POST"
                                    action={`/checkout/${session.public_id}/cancel`}
                                    className="w-full"
                                >
                                    <input
                                        type="hidden"
                                        name="_token"
                                        value={
                                            (document.querySelector('meta[name="csrf-token"]') as
                                                | HTMLMetaElement
                                                | null
                                            )?.content ?? ''
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full text-muted-foreground hover:text-destructive"
                                    >
                                        Cancel checkout
                                    </Button>
                                </form>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

function GatewayTile({
    gateway,
    selected,
    onSelect,
}: {
    gateway: Gateway;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={
                'flex w-full items-start gap-3 rounded-lg border p-4 text-left transition-colors ' +
                (selected
                    ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                    : 'hover:bg-muted/40')
            }
        >
            <div className="mt-0.5">
                {selected ? (
                    <Check className="size-5 text-primary" />
                ) : (
                    <CreditCard className="size-5 text-muted-foreground" />
                )}
            </div>
            <div className="flex-1">
                <div className="flex items-center justify-between gap-3">
                    <div className="font-medium">{gateway.name}</div>
                    {gateway.meta.driver_status === 'planned' ? (
                        <Badge variant="outline" className="text-xs">
                            Beta
                        </Badge>
                    ) : null}
                </div>
                {gateway.meta.description ? (
                    <div className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                        {gateway.meta.description}
                    </div>
                ) : null}
                <div className="mt-1.5 flex flex-wrap gap-1">
                    {(gateway.meta.regions ?? []).slice(0, 3).map((r) => (
                        <Badge key={r} variant="outline" className="text-xs">
                            {r}
                        </Badge>
                    ))}
                </div>
            </div>
        </button>
    );
}
