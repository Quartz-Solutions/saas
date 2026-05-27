import { Link, usePage } from '@inertiajs/react';
import SeoMeta from '@/components/marketing/seo-meta';
import {
    ArrowRight,
    Building,
    Code,
    CreditCard,
    Lock,
    Mail,
    Shield
    
} from 'lucide-react';
import type {LucideIcon} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { login } from '@/routes';
import marketingRoutes from '@/routes/marketing';
import { show as getStarted } from '@/actions/App/Http/Controllers/Onboarding/GetStartedController';

type Feature = {
    title: string;
    description: string;
    icon: string;
};

type Props = {
    features: Feature[];
};

type SharedProps = {
    name: string;
    canRegister?: boolean;
};

const iconMap: Record<string, LucideIcon> = {
    building: Building,
    'credit-card': CreditCard,
    shield: Shield,
    code: Code,
    mail: Mail,
    lock: Lock,
};

export default function MarketingHome({ features }: Props) {
    const { name, canRegister } = usePage<SharedProps>().props;

    return (
        <>
            <SeoMeta
                pageTitle="Welcome"
                title={`${name} — ship your SaaS in days, not months`}
                description={`${name} — the Laravel + Inertia + React SaaS boilerplate. Multi-tenant, multi-gateway billing, admin scope, and more.`}
            />

            {/* Hero */}
            <section
                className="relative mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-28"
                data-test="marketing-hero"
            >
                <div className="mx-auto max-w-3xl text-center">
                    <p className="mb-4 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        SaaS Boilerplate
                    </p>
                    <h1 className="text-4xl font-semibold tracking-tight text-foreground md:text-6xl">
                        Ship your SaaS in days, not months.
                    </h1>
                    <p className="mt-6 text-lg text-muted-foreground md:text-xl">
                        {name} gives you multi-tenancy, multi-gateway billing, an admin
                        scope, and typed routes — production-shaped from commit one.
                    </p>
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        {canRegister !== false ? (
                            <Button asChild size="lg" data-test="hero-cta-primary">
                                <Link href={getStarted().url}>
                                    Get started <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                        ) : (
                            <Button asChild size="lg" data-test="hero-cta-primary">
                                <Link href={login().url}>
                                    Log in <ArrowRight className="ml-2 size-4" />
                                </Link>
                            </Button>
                        )}
                        <Button asChild size="lg" variant="ghost" data-test="hero-cta-secondary">
                            <Link href={marketingRoutes.pricing().url}>See pricing</Link>
                        </Button>
                    </div>
                </div>
            </section>

            {/* Features */}
            <section
                id="features"
                className="border-y border-border/40 bg-muted/20 py-20"
                data-test="marketing-features"
            >
                <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                    <div className="mx-auto max-w-2xl text-center">
                        <h2 className="text-3xl font-semibold md:text-4xl">
                            Everything a SaaS needs, on day one
                        </h2>
                        <p className="mt-4 text-muted-foreground">
                            Auth, tenancy, billing, admin, notifications, compliance — built
                            in. Fork the boilerplate and start shipping the part that's
                            actually different.
                        </p>
                    </div>

                    <div className="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {features.map((feature) => {
                            const Icon = iconMap[feature.icon] ?? Shield;

                            return (
                                <Card
                                    key={feature.title}
                                    className={cn('h-full border-border/60')}
                                    data-test="feature-card"
                                >
                                    <CardContent className="p-6">
                                        <div className="mb-4 flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        <h3 className="text-lg font-semibold">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {feature.description}
                                        </p>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </div>
            </section>

            {/* Social proof / trust */}
            <section className="py-16" data-test="marketing-trust">
                <div className="mx-auto w-full max-w-4xl px-4 text-center md:px-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Built on a stack you can trust
                    </p>
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-lg font-semibold text-muted-foreground">
                        <span>Laravel 13</span>
                        <span>Inertia 3</span>
                        <span>React 19</span>
                        <span>TypeScript</span>
                        <span>Tailwind 4</span>
                        <span>Postgres 16</span>
                    </div>
                </div>
            </section>

            {/* Pricing CTA */}
            <section
                className="border-t border-border/40 bg-gradient-to-b from-muted/20 to-background py-20"
                data-test="marketing-pricing-cta"
            >
                <div className="mx-auto max-w-3xl px-4 text-center md:px-6">
                    <h2 className="text-3xl font-semibold md:text-4xl">
                        Simple, transparent pricing
                    </h2>
                    <p className="mt-4 text-muted-foreground">
                        Start free. Upgrade when your SaaS does.
                    </p>
                    <div className="mt-8 flex justify-center">
                        <Button asChild size="lg">
                            <Link href={marketingRoutes.pricing().url}>
                                View plans <ArrowRight className="ml-2 size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </>
    );
}
