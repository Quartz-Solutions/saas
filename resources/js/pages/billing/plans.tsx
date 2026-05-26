import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, ExternalLink, Sparkles } from 'lucide-react';
import { useState } from 'react';
import BillingController from '@/actions/App/Http/Controllers/Billing/BillingController';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type PlanRow = {
    slug: string;
    name: string;
    description: string;
    price_cents: number;
    currency: string;
    interval: string;
    features: string[];
    cta: string;
    highlighted: boolean;
    is_current: boolean;
};

type Subscription = {
    id: number;
    plan_slug: string | null;
    plan_name: string | null;
    status: string;
    gateway: string;
    currency: string;
    unit_amount_cents: number;
    current_period_end: string | null;
    trial_ends_at: string | null;
    cancel_at_period_end: boolean;
};

type Gateway = {
    id: string;
    name: string;
};

type Props = {
    plans: PlanRow[];
    subscription: Subscription | null;
    gateways: Gateway[];
    default_gateway: string;
};

function formatMoney(cents: number, currency: string): string {
    if (cents === 0) return 'Free';
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: 0,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
}

export default function BillingPlans({ plans, subscription, gateways, default_gateway }: Props) {
    const { currentTenant } = usePage<{ currentTenant: { slug: string } | null }>().props;
    const tenantSlug = currentTenant?.slug ?? '';

    const [cancelOpen, setCancelOpen] = useState(false);

    return (
        <>
            <Head title="Billing — Plans" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Plans & billing"
                        description="Pick the plan that fits this tenant. Upgrades are prorated; downgrades take effect at the end of the period."
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link
                                href={tenantRoutes.billing.invoices.index({ tenantSlug })}
                                data-test="link-invoices"
                            >
                                Invoices
                            </Link>
                        </Button>
                        {gateways.some((g) => g.id === 'stripe') && subscription && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={tenantRoutes.billing.portal({ tenantSlug })}
                                    data-test="link-portal"
                                >
                                    <ExternalLink className="mr-1.5 size-4" />
                                    Customer portal
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {subscription && (
                    <Card data-test="current-subscription">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-3">
                                Current subscription
                                <Badge variant={subscription.status === 'active' ? 'default' : 'outline'}>
                                    {subscription.status}
                                </Badge>
                                {subscription.cancel_at_period_end && (
                                    <Badge variant="destructive">Cancelling</Badge>
                                )}
                            </CardTitle>
                            <CardDescription>
                                {subscription.plan_name ?? 'Plan'} —{' '}
                                {formatMoney(subscription.unit_amount_cents, subscription.currency)} / month via{' '}
                                {subscription.gateway}.
                                {subscription.current_period_end && (
                                    <> Renews {new Date(subscription.current_period_end).toLocaleDateString()}.</>
                                )}
                                {subscription.trial_ends_at && (
                                    <> Trial ends {new Date(subscription.trial_ends_at).toLocaleDateString()}.</>
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardFooter className="gap-2">
                            {subscription.cancel_at_period_end ? (
                                <Form
                                    {...BillingController.resume.form({ tenantSlug })}
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button type="submit" variant="secondary" disabled={processing}>
                                            {processing && <Spinner />}
                                            Resume subscription
                                        </Button>
                                    )}
                                </Form>
                            ) : (
                                <Button
                                    variant="destructive"
                                    onClick={() => setCancelOpen(true)}
                                    data-test="btn-cancel"
                                >
                                    Cancel subscription
                                </Button>
                            )}
                        </CardFooter>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <Card
                            key={plan.slug}
                            className={cn(
                                'flex flex-col',
                                plan.highlighted && 'border-primary shadow-md',
                                plan.is_current && 'ring-2 ring-primary',
                            )}
                            data-test={`plan-${plan.slug}`}
                        >
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {plan.name}
                                    {plan.highlighted && (
                                        <Sparkles className="size-4 text-primary" />
                                    )}
                                    {plan.is_current && <Badge>Current</Badge>}
                                </CardTitle>
                                <CardDescription>{plan.description}</CardDescription>
                            </CardHeader>
                            <CardContent className="flex-1 space-y-4">
                                <div className="text-3xl font-semibold tracking-tight">
                                    {formatMoney(plan.price_cents, plan.currency)}
                                    {plan.price_cents > 0 && (
                                        <span className="ml-1 text-sm font-normal text-muted-foreground">
                                            / {plan.interval}
                                        </span>
                                    )}
                                </div>
                                <ul className="space-y-1.5 text-sm">
                                    {plan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                            <CardFooter>
                                <Form
                                    {...BillingController.subscribe.form({ tenantSlug })}
                                    options={{ preserveScroll: true }}
                                    className="w-full"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="plan" value={plan.slug} />
                                            <input type="hidden" name="gateway" value={default_gateway} />
                                            <Button
                                                type="submit"
                                                variant={plan.highlighted ? 'default' : 'outline'}
                                                className="w-full"
                                                disabled={processing || plan.is_current}
                                            >
                                                {processing && <Spinner />}
                                                {plan.is_current ? 'Current plan' : plan.cta}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardFooter>
                        </Card>
                    ))}
                </div>

                {gateways.length === 0 && (
                    <Card className="border-amber-200 bg-amber-50 dark:bg-amber-950">
                        <CardHeader>
                            <CardTitle>No payment gateway configured</CardTitle>
                            <CardDescription>
                                Set <code className="font-mono">STRIPE_SECRET</code> in <code className="font-mono">.env</code>{' '}
                                to enable paid plan checkout. The free plan still works without a gateway.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </div>

            <CancelDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                tenantSlug={tenantSlug}
            />
        </>
    );
}

function CancelDialog({
    open,
    onOpenChange,
    tenantSlug,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    tenantSlug: string;
}) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Cancel subscription?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Your tenant will keep access until the end of the current
                        billing period. Tell us why so we can improve the product.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <Form
                    {...BillingController.cancel.form({ tenantSlug })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="immediately" value="0" />
                            <div className="grid gap-2">
                                <Label htmlFor="cancel-reason">Reason (optional)</Label>
                                <Textarea
                                    id="cancel-reason"
                                    name="reason"
                                    rows={4}
                                    placeholder="Tell us what we could have done differently."
                                />
                                {errors.reason && (
                                    <p className="text-sm text-destructive">{errors.reason}</p>
                                )}
                            </div>
                            <AlertDialogFooter>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Keep subscription
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-cancel"
                                >
                                    {processing && <Spinner />}
                                    Cancel subscription
                                </Button>
                            </AlertDialogFooter>
                        </>
                    )}
                </Form>
            </AlertDialogContent>
        </AlertDialog>
    );
}

BillingPlans.layout = {
    breadcrumbs: ({
        currentTenant,
    }: {
        currentTenant: { slug: string; name: string } | null;
    }) => {
        const slug = currentTenant?.slug ?? '';
        return [
            {
                title: currentTenant?.name ?? 'Tenant',
                href: tenantRoutes.dashboard({ tenantSlug: slug }),
            },
            { title: 'Billing', href: tenantRoutes.billing.plans({ tenantSlug: slug }) },
        ];
    },
};
