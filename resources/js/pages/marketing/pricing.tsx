import { Head, Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { register, login } from '@/routes';

type Plan = {
    name: string;
    slug: string;
    description: string;
    price_cents: number;
    currency: string;
    interval: string;
    features: string[];
    cta: string;
    highlighted: boolean;
};

type Props = {
    plans: Plan[];
    trialDays: number;
    defaultCurrency: string;
};

type SharedProps = {
    canRegister?: boolean;
};

function formatPrice(cents: number, currency: string): string {
    if (cents === 0) {
        return 'Free';
    }

    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        maximumFractionDigits: cents % 100 === 0 ? 0 : 2,
    });

    return formatter.format(cents / 100);
}

export default function MarketingPricing({ plans, trialDays }: Props) {
    const { canRegister } = usePage<SharedProps>().props;
    const ctaHref = canRegister !== false ? register().url : login().url;

    return (
        <>
            <Head title="Pricing">
                <meta
                    name="description"
                    content="Simple, transparent pricing for every stage of your SaaS."
                />
            </Head>

            <section
                className="mx-auto w-full max-w-6xl px-4 py-16 md:px-6 md:py-24"
                data-test="pricing-page"
            >
                <div className="mx-auto max-w-2xl text-center">
                    <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">
                        Pricing built to scale with you
                    </h1>
                    <p className="mt-4 text-muted-foreground">
                        Start free. Upgrade when you're ready. Cancel any time.
                        {trialDays > 0 && ` All paid plans include a ${trialDays}-day free trial.`}
                    </p>
                </div>

                <div className="mt-12 grid gap-6 md:grid-cols-3" data-test="pricing-grid">
                    {plans.map((plan) => (
                        <Card
                            key={plan.slug}
                            className={cn(
                                'flex h-full flex-col border-border/60',
                                plan.highlighted && 'border-primary shadow-lg ring-1 ring-primary',
                            )}
                            data-test={`pricing-card-${plan.slug}`}
                        >
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-2xl">{plan.name}</CardTitle>
                                    {plan.highlighted && (
                                        <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                            Popular
                                        </span>
                                    )}
                                </div>
                                <CardDescription>{plan.description}</CardDescription>

                                <div className="mt-4 flex items-baseline gap-1">
                                    <span
                                        className="text-4xl font-semibold tracking-tight"
                                        data-test={`pricing-card-${plan.slug}-price`}
                                    >
                                        {formatPrice(plan.price_cents, plan.currency)}
                                    </span>
                                    {plan.price_cents > 0 && (
                                        <span className="text-sm text-muted-foreground">
                                            /{plan.interval}
                                        </span>
                                    )}
                                </div>
                            </CardHeader>

                            <CardContent className="flex flex-1 flex-col">
                                <ul className="space-y-3 text-sm">
                                    {plan.features.map((feature) => (
                                        <li key={feature} className="flex items-start gap-2">
                                            <Check className="mt-0.5 size-4 shrink-0 text-primary" />
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>

                                <div className="mt-auto pt-6">
                                    <Button
                                        asChild
                                        className="w-full"
                                        variant={plan.highlighted ? 'default' : 'outline'}
                                        data-test={`pricing-card-${plan.slug}-cta`}
                                    >
                                        <Link href={ctaHref}>{plan.cta}</Link>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <p className="mt-10 text-center text-sm text-muted-foreground">
                    Need something different?{' '}
                    <a
                        href="mailto:sales@example.com"
                        className="font-medium text-foreground underline underline-offset-4"
                    >
                        Talk to sales
                    </a>
                    .
                </p>
            </section>
        </>
    );
}
