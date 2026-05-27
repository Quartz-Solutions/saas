import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Check, Loader2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Plan = {
    slug: string;
    name: string;
    description: string;
    price_cents: number;
    currency: string;
    interval: string;
    trial_days: number;
    features: string[];
};

type Props = {
    plans: Plan[];
    selectedPlanSlug?: string | null;
};

const formatMoney = (cents: number, currency: string) => {
    try {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(cents / 100);
    } catch {
        return `${(cents / 100).toFixed(2)} ${currency}`;
    }
};

export default function GetStarted({ plans, selectedPlanSlug }: Props) {
    const preselected = selectedPlanSlug
        ? plans.find((p) => p.slug === selectedPlanSlug)
        : undefined;
    const initialPlan = preselected ?? plans.find((p) => p.price_cents > 0) ?? plans[0];
    const [selectedPlan, setSelectedPlan] = useState<string>(initialPlan?.slug ?? '');

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        tenant_name: '',
        tenant_slug: '',
        plan_slug: selectedPlan,
    });

    const pickPlan = (slug: string) => {
        setSelectedPlan(slug);
        setData('plan_slug', slug);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/get-started');
    };

    const chosen = plans.find((p) => p.slug === selectedPlan);
    const isPaid = (chosen?.price_cents ?? 0) > 0;

    return (
        <>
            <Head title="Get started" />

            <div className="mx-auto max-w-5xl px-4 py-12">
                <div className="mb-10 text-center">
                    <Heading
                        title="Get started"
                        description="Create your account, your workspace, and pick a plan in one step."
                    />
                </div>

                <form onSubmit={submit} className="grid gap-8 lg:grid-cols-2">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">1. Account</CardTitle>
                                <CardDescription>
                                    Your personal login. You can invite teammates later.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Your name</Label>
                                    <Input
                                        id="name"
                                        autoComplete="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                        autoFocus
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Password</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            autoComplete="new-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">Confirm</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            autoComplete="new-password"
                                            value={data.password_confirmation}
                                            onChange={(e) =>
                                                setData('password_confirmation', e.target.value)
                                            }
                                            required
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">2. Workspace</CardTitle>
                                <CardDescription>
                                    The tenant your team will work in. You can rename later.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant_name">Workspace name</Label>
                                    <Input
                                        id="tenant_name"
                                        value={data.tenant_name}
                                        onChange={(e) => setData('tenant_name', e.target.value)}
                                        required
                                        placeholder="Acme Corp"
                                    />
                                    <InputError message={errors.tenant_name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="tenant_slug">URL slug (optional)</Label>
                                    <Input
                                        id="tenant_slug"
                                        value={data.tenant_slug}
                                        onChange={(e) => setData('tenant_slug', e.target.value)}
                                        placeholder="acme"
                                        pattern="[a-z0-9-]+"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Auto-generated from name if blank. Used in URLs:
                                        <code className="ml-1 text-xs">/t/{data.tenant_slug || 'your-slug'}/...</code>
                                    </p>
                                    <InputError message={errors.tenant_slug} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Gateway picker happens on /checkout/{session} after this form submits.
                            Paid plans land there; free plans skip checkout entirely. */}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {isPaid ? '3. Plan' : '3. Pick a plan'}
                                </CardTitle>
                                <CardDescription>
                                    Free plans skip checkout entirely. Paid plans redirect to
                                    secure gateway-hosted payment.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {plans.map((p) => {
                                    const active = selectedPlan === p.slug;
                                    return (
                                        <button
                                            type="button"
                                            key={p.slug}
                                            onClick={() => pickPlan(p.slug)}
                                            className={
                                                'w-full rounded-lg border p-4 text-left transition-colors ' +
                                                (active
                                                    ? 'border-primary bg-primary/5 ring-2 ring-primary/30'
                                                    : 'hover:bg-muted/40')
                                            }
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <div className="font-medium">{p.name}</div>
                                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                                        {p.description}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-mono text-base font-semibold">
                                                        {p.price_cents === 0 ? (
                                                            'Free'
                                                        ) : (
                                                            <>
                                                                {formatMoney(
                                                                    p.price_cents,
                                                                    p.currency,
                                                                )}
                                                                <span className="ml-0.5 text-xs font-normal text-muted-foreground">
                                                                    /{p.interval}
                                                                </span>
                                                            </>
                                                        )}
                                                    </div>
                                                    {p.trial_days > 0 ? (
                                                        <Badge variant="secondary" className="mt-1 text-xs">
                                                            {p.trial_days}-day trial
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </div>
                                            {active && p.features.length > 0 ? (
                                                <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                                                    {p.features.slice(0, 6).map((f, i) => (
                                                        <li key={i} className="flex items-start gap-1.5">
                                                            <Check className="mt-0.5 size-3 text-emerald-500" />
                                                            <span>{f}</span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            ) : null}
                                        </button>
                                    );
                                })}
                                <input type="hidden" name="plan_slug" value={data.plan_slug} />
                                <InputError message={errors.plan_slug} />
                            </CardContent>
                            <CardFooter className="flex flex-col gap-2">
                                <Button
                                    type="submit"
                                    disabled={processing || !selectedPlan}
                                    className="w-full"
                                    size="lg"
                                >
                                    {processing ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <ArrowRight className="size-4" />
                                    )}
                                    {isPaid
                                        ? `Continue to checkout — ${chosen ? formatMoney(chosen.price_cents, chosen.currency) : ''}`
                                        : 'Create my workspace'}
                                </Button>
                                <p className="text-center text-xs text-muted-foreground">
                                    By continuing you agree to the{' '}
                                    <Link
                                        href="/legal/terms"
                                        className="underline hover:text-foreground"
                                    >
                                        Terms
                                    </Link>{' '}
                                    and{' '}
                                    <Link
                                        href="/legal/privacy"
                                        className="underline hover:text-foreground"
                                    >
                                        Privacy
                                    </Link>
                                    .
                                </p>
                            </CardFooter>
                        </Card>

                        <p className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <Link href="/login" className="font-medium underline">
                                Sign in
                            </Link>
                        </p>
                    </div>
                </form>
            </div>
        </>
    );
}
