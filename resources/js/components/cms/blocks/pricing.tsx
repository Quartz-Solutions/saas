import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import marketingRoutes from '@/routes/marketing';
import type { BlockComponentProps, CmsRefs, PlanRef } from '../types';

type Attrs = {
    eyebrow?: string;
    title?: string;
    subtitle?: string;
    plan_slugs?: string[];
    highlight_slug?: string | null;
};

type ShareProps = { cmsRefs?: CmsRefs };

function formatMoney(cents: number, currency: string): string {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
        }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
}

export default function PricingBlock({ block }: BlockComponentProps<Attrs>) {
    const { cmsRefs } = usePage<ShareProps>().props;
    const slugs = block.attrs.plan_slugs ?? [];
    const planMap = cmsRefs?.plans ?? {};
    const plans: PlanRef[] = (
        slugs.length > 0 ? slugs.map((s) => planMap[s]).filter(Boolean) : Object.values(planMap)
    ) as PlanRef[];

    if (plans.length === 0) {
        return (
            <section className="py-16 text-center text-muted-foreground" data-test="block-pricing-empty">
                Configure plans in <Link href={marketingRoutes.pricing().url} className="underline">/pricing</Link>.
            </section>
        );
    }

    return (
        <section className="py-20" data-test="block-pricing">
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                {(block.attrs.title || block.attrs.subtitle) && (
                    <div className="mx-auto max-w-2xl text-center">
                        {block.attrs.eyebrow && (
                            <p className="mb-2 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                {block.attrs.eyebrow}
                            </p>
                        )}
                        {block.attrs.title && (
                            <h2 className="text-3xl font-semibold md:text-4xl">{block.attrs.title}</h2>
                        )}
                        {block.attrs.subtitle && (
                            <p className="mt-4 text-muted-foreground">{block.attrs.subtitle}</p>
                        )}
                    </div>
                )}

                <div className="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {plans.map((plan) => {
                        const highlight = block.attrs.highlight_slug === plan.slug;

                        return (
                            <Card
                                key={plan.slug}
                                className={cn(
                                    'flex h-full flex-col border-border/60',
                                    highlight && 'border-primary shadow-md ring-1 ring-primary/30',
                                )}
                                data-test="plan-card"
                            >
                                <CardHeader>
                                    <CardTitle>{plan.name}</CardTitle>
                                    <CardDescription>{plan.description}</CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-1 flex-col">
                                    <div className="mb-4">
                                        <span className="text-3xl font-semibold">
                                            {formatMoney(plan.price_cents, plan.currency)}
                                        </span>
                                        {plan.price_cents > 0 && (
                                            <span className="text-sm text-muted-foreground"> / {plan.interval}</span>
                                        )}
                                    </div>
                                    <ul className="mb-6 space-y-2 text-sm">
                                        {plan.features.map((f) => (
                                            <li key={f} className="flex items-start gap-2">
                                                <Check className="mt-0.5 size-4 text-primary" />
                                                <span>{f}</span>
                                            </li>
                                        ))}
                                    </ul>
                                    <div className="mt-auto">
                                        <Button asChild className="w-full" variant={highlight ? 'default' : 'outline'}>
                                            <Link href={`/get-started?plan=${plan.slug}`}>{plan.cta}</Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
