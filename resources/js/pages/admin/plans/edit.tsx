import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import PlansController from '@/actions/App/Http/Controllers/Admin/PlansController';
import { index as plansIndex } from '@/routes/admin/plans';

const UNLIMITED = -1;

type FeatureValue = boolean | number;
type FeaturesMap = Record<string, FeatureValue>;

type Plan = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price_cents: number;
    currency: string;
    billing_period: string;
    billing_interval: number;
    trial_days: number;
    features: FeaturesMap;
    gateway_ids: Record<string, string | null>;
    is_active: boolean;
    is_public: boolean;
    sort_order: number;
    active_subscriptions_count?: number;
};

type Currency = { code: string; name: string };

type FeatureCatalogItem = {
    slug: string;
    name: string;
    description: string | null;
    type: 'boolean' | 'quota';
    unit: string | null;
    unlimited_label: string | null;
};

type FeatureCatalogGroup = {
    category: string;
    items: FeatureCatalogItem[];
};

type Props = {
    plan: Plan | null;
    currencies: Currency[];
    featureCatalog: FeatureCatalogGroup[];
};

export default function PlanEdit({ plan, currencies, featureCatalog }: Props) {
    const isEdit = plan !== null;

    const [name, setName] = useState(plan?.name ?? '');
    const [slug, setSlug] = useState(plan?.slug ?? '');
    const [description, setDescription] = useState(plan?.description ?? '');
    const [priceDollars, setPriceDollars] = useState(
        plan ? (plan.price_cents / 100).toFixed(2) : '0.00',
    );
    const [currency, setCurrency] = useState(plan?.currency ?? 'USD');
    const [billingPeriod, setBillingPeriod] = useState(plan?.billing_period ?? 'month');
    const [billingInterval, setBillingInterval] = useState(plan?.billing_interval ?? 1);
    const [trialDays, setTrialDays] = useState(plan?.trial_days ?? 0);
    const [features, setFeatures] = useState<FeaturesMap>(
        () => (plan?.features as FeaturesMap | undefined) ?? {},
    );
    const [isActive, setIsActive] = useState(plan?.is_active ?? true);
    const [isPublic, setIsPublic] = useState(plan?.is_public ?? true);
    const [sortOrder, setSortOrder] = useState(plan?.sort_order ?? 0);

    const setFeature = (slug: string, value: FeatureValue | null) => {
        setFeatures((prev) => {
            const next = { ...prev };
            if (value === null) {
                delete next[slug];
            } else {
                next[slug] = value;
            }
            return next;
        });
    };

    const selectedSlugs = Object.keys(features);

    const priceCents = Math.round(Number(priceDollars || '0') * 100);

    const action = isEdit
        ? PlansController.update.form({ plan: plan.id })
        : PlansController.store.form();

    return (
        <>
            <Head title={isEdit ? `${plan?.name} — Plans` : 'New plan — Plans'} />

            <div className="mb-6 flex items-center gap-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={plansIndex()}>
                        <ArrowLeft className="size-4" />
                        Back to plans
                    </Link>
                </Button>
            </div>

            <Heading
                title={isEdit ? plan!.name : 'New plan'}
                description={
                    isEdit
                        ? `Slug: ${plan!.slug}. Stripe Price IDs regenerate when price/currency/period changes.`
                        : 'Saving creates the Stripe Price automatically when Stripe is enabled.'
                }
            />

            <Form {...action} disableWhileProcessing resetOnSuccess={false}>
                {({ processing, errors }) => (
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="space-y-6 lg:col-span-2">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Details</CardTitle>
                                    <CardDescription>
                                        Shown on the public pricing page.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            value={name}
                                            onChange={(e) => setName(e.target.value)}
                                            required
                                            autoFocus
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">Slug</Label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            value={slug}
                                            onChange={(e) => setSlug(e.target.value)}
                                            placeholder="Auto-generated from name if blank"
                                        />
                                        <InputError message={errors.slug} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={3}
                                            value={description ?? ''}
                                            onChange={(e) => setDescription(e.target.value)}
                                        />
                                        <InputError message={errors.description} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Pricing</CardTitle>
                                    <CardDescription>
                                        Monetary values are integer cents in the database.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-5 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="price">Price</Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id="price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={priceDollars}
                                                onChange={(e) => setPriceDollars(e.target.value)}
                                                required
                                                className="font-mono"
                                            />
                                            <input
                                                type="hidden"
                                                name="price_cents"
                                                value={priceCents}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            = {priceCents} cents
                                        </p>
                                        <InputError message={errors.price_cents} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">Currency</Label>
                                        <Select value={currency} onValueChange={setCurrency}>
                                            <SelectTrigger id="currency" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {currencies.map((c) => (
                                                    <SelectItem key={c.code} value={c.code}>
                                                        {c.code} — {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input type="hidden" name="currency" value={currency} />
                                        <InputError message={errors.currency} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_period">Billing period</Label>
                                        <Select
                                            value={billingPeriod}
                                            onValueChange={setBillingPeriod}
                                        >
                                            <SelectTrigger id="billing_period" className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="day">Daily</SelectItem>
                                                <SelectItem value="week">Weekly</SelectItem>
                                                <SelectItem value="month">Monthly</SelectItem>
                                                <SelectItem value="year">Yearly</SelectItem>
                                                <SelectItem value="one_time">One-time</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="billing_period"
                                            value={billingPeriod}
                                        />
                                        <InputError message={errors.billing_period} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_interval">Interval (every N)</Label>
                                        <Input
                                            id="billing_interval"
                                            name="billing_interval"
                                            type="number"
                                            min="1"
                                            max="24"
                                            value={billingInterval}
                                            onChange={(e) =>
                                                setBillingInterval(Number(e.target.value))
                                            }
                                            required
                                            className="font-mono"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            e.g. interval=3 + period=month → quarterly
                                        </p>
                                        <InputError message={errors.billing_interval} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="trial_days">Trial days</Label>
                                        <Input
                                            id="trial_days"
                                            name="trial_days"
                                            type="number"
                                            min="0"
                                            max="365"
                                            value={trialDays}
                                            onChange={(e) => setTrialDays(Number(e.target.value))}
                                            required
                                            className="font-mono"
                                        />
                                        <InputError message={errors.trial_days} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="sort_order">Sort order</Label>
                                        <Input
                                            id="sort_order"
                                            name="sort_order"
                                            type="number"
                                            min="0"
                                            value={sortOrder}
                                            onChange={(e) => setSortOrder(Number(e.target.value))}
                                            required
                                            className="font-mono"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Lower numbers appear first on /pricing.
                                        </p>
                                        <InputError message={errors.sort_order} />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Features</CardTitle>
                                    <CardDescription>
                                        Toggle a feature on, then set its limit. Quotas accept a
                                        number or "Unlimited"; booleans are present/absent. The
                                        catalog lives in <code className="text-xs">config/billing.php</code>.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {featureCatalog.map((group) => (
                                        <div key={group.category} className="space-y-3">
                                            <h3 className="text-sm font-medium text-muted-foreground">
                                                {group.category}
                                            </h3>
                                            <div className="space-y-2">
                                                {group.items.map((item) =>
                                                    item.type === 'quota' ? (
                                                        <QuotaRow
                                                            key={item.slug}
                                                            item={item}
                                                            value={features[item.slug]}
                                                            setValue={(v) => setFeature(item.slug, v)}
                                                            error={errors[`features.${item.slug}`]}
                                                        />
                                                    ) : (
                                                        <BooleanRow
                                                            key={item.slug}
                                                            item={item}
                                                            checked={Boolean(features[item.slug])}
                                                            onToggle={(checked) =>
                                                                setFeature(
                                                                    item.slug,
                                                                    checked ? true : null,
                                                                )
                                                            }
                                                            error={errors[`features.${item.slug}`]}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    {/* Hidden inputs — submit map shape as features[slug]=value */}
                                    {selectedSlugs.map((slug) => {
                                        const value = features[slug];
                                        const submitValue =
                                            value === true
                                                ? '1'
                                                : value === false
                                                  ? '0'
                                                  : String(value);
                                        return (
                                            <input
                                                key={slug}
                                                type="hidden"
                                                name={`features[${slug}]`}
                                                value={submitValue}
                                            />
                                        );
                                    })}
                                    <InputError message={errors.features} />
                                    <p className="text-xs text-muted-foreground">
                                        {selectedSlugs.length} feature(s) on this plan.
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Visibility</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                        <Label htmlFor="is_active" className="text-sm">
                                            Active
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="is_active"
                                            value={isActive ? '1' : '0'}
                                        />
                                        <Switch
                                            id="is_active"
                                            checked={isActive}
                                            onCheckedChange={setIsActive}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Inactive plans cannot be subscribed to. Existing
                                        subscriptions keep working.
                                    </p>
                                    <div className="flex items-center justify-between rounded-md border bg-muted/30 px-3 py-2">
                                        <Label htmlFor="is_public" className="text-sm">
                                            Public on /pricing
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="is_public"
                                            value={isPublic ? '1' : '0'}
                                        />
                                        <Switch
                                            id="is_public"
                                            checked={isPublic}
                                            onCheckedChange={setIsPublic}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Off → invite-only / legacy / custom enterprise plan.
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Gateway sync</CardTitle>
                                    <CardDescription>
                                        Read-only — created automatically on save.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>Stripe Price ID</span>
                                        {plan?.gateway_ids?.stripe ? (
                                            <code className="rounded bg-muted px-2 py-0.5 text-xs">
                                                {plan.gateway_ids.stripe}
                                            </code>
                                        ) : (
                                            <Badge variant="outline">Not synced</Badge>
                                        )}
                                    </div>
                                    {plan?.gateway_ids?.stripe_product ? (
                                        <div className="flex items-center justify-between text-sm">
                                            <span>Stripe Product</span>
                                            <code className="rounded bg-muted px-2 py-0.5 text-xs">
                                                {plan.gateway_ids.stripe_product}
                                            </code>
                                        </div>
                                    ) : null}
                                </CardContent>
                                <CardFooter>
                                    <Button type="submit" disabled={processing} className="w-full">
                                        {processing ? (
                                            <Spinner className="size-4" />
                                        ) : (
                                            <Save className="size-4" />
                                        )}
                                        {isEdit ? 'Save plan' : 'Create plan'}
                                    </Button>
                                </CardFooter>
                            </Card>

                            {isEdit && (plan?.active_subscriptions_count ?? 0) > 0 ? (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">
                                            Active subscriptions
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-sm">
                                            {plan?.active_subscriptions_count} tenant
                                            {plan?.active_subscriptions_count === 1 ? '' : 's'} on
                                            this plan. Price changes create a new Stripe Price;
                                            existing subscribers keep billing on the old one until
                                            they upgrade.
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : null}
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

function BooleanRow({
    item,
    checked,
    onToggle,
    error,
}: {
    item: FeatureCatalogItem;
    checked: boolean;
    onToggle: (checked: boolean) => void;
    error?: string;
}) {
    const id = `feature-${item.slug}`;
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <label htmlFor={id} className="flex cursor-pointer items-start gap-3">
                <Checkbox
                    id={id}
                    checked={checked}
                    onCheckedChange={(v) => onToggle(Boolean(v))}
                    className="mt-0.5"
                />
                <div className="flex-1">
                    <div className="text-sm font-medium">{item.name}</div>
                    <div className="font-mono text-xs text-muted-foreground">{item.slug}</div>
                    {item.description ? (
                        <div className="mt-0.5 text-xs text-muted-foreground">
                            {item.description}
                        </div>
                    ) : null}
                </div>
                <Badge variant="outline" className="text-xs">
                    boolean
                </Badge>
            </label>
            <InputError message={error} />
        </div>
    );
}

function QuotaRow({
    item,
    value,
    setValue,
    error,
}: {
    item: FeatureCatalogItem;
    value: FeatureValue | undefined;
    setValue: (v: FeatureValue | null) => void;
    error?: string;
}) {
    const id = `feature-${item.slug}`;
    const included = value !== undefined;
    const unlimited = value === UNLIMITED;
    const numericValue = typeof value === 'number' && value !== UNLIMITED ? value : 1;

    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <div className="flex items-start gap-3">
                <Checkbox
                    id={id}
                    checked={included}
                    onCheckedChange={(v) => setValue(v ? numericValue : null)}
                    className="mt-0.5"
                />
                <div className="flex-1">
                    <label htmlFor={id} className="cursor-pointer">
                        <div className="text-sm font-medium">{item.name}</div>
                        <div className="font-mono text-xs text-muted-foreground">{item.slug}</div>
                        {item.description ? (
                            <div className="mt-0.5 text-xs text-muted-foreground">
                                {item.description}
                            </div>
                        ) : null}
                    </label>

                    {included ? (
                        <div className="mt-3 flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <Input
                                    type="number"
                                    min="1"
                                    value={unlimited ? '' : numericValue}
                                    placeholder={unlimited ? '∞' : '1'}
                                    onChange={(e) => {
                                        const n = Number(e.target.value);
                                        if (Number.isFinite(n) && n > 0) {
                                            setValue(n);
                                        }
                                    }}
                                    disabled={unlimited}
                                    className="w-24 font-mono"
                                />
                                <span className="text-xs text-muted-foreground">
                                    {item.unit ?? ''}
                                </span>
                            </div>
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <Switch
                                    checked={unlimited}
                                    onCheckedChange={(v) =>
                                        setValue(v ? UNLIMITED : numericValue)
                                    }
                                />
                                <span>Unlimited</span>
                            </label>
                        </div>
                    ) : null}
                </div>
                <Badge variant="outline" className="text-xs">
                    quota
                </Badge>
            </div>
            <InputError message={error} />
        </div>
    );
}
