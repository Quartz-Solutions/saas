import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleDashed,
    ExternalLink,
    Eye,
    EyeOff,
    Save,
} from 'lucide-react';
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { index as gatewaysIndex } from '@/routes/admin/gateways';

const SECRET_MASK = '••••••••';

type FieldType = 'string' | 'secret' | 'email' | 'url' | 'int' | 'bool' | 'select';

type Field = {
    key: string;
    label: string;
    type: FieldType;
    options: Record<string, string> | null;
    help: string | null;
    is_secret: boolean;
    has_value: boolean;
    value: string | null;
};

type Gateway = {
    id: string;
    name: string;
    description: string | null;
    regions: string[];
    capabilities: string[];
    driver_status: 'shipped' | 'planned';
    documentation_url: string | null;
    enabled: boolean;
    configured: boolean;
    has_fields: boolean;
    active_subscriptions: number;
};

type Props = {
    gateway: Gateway;
    fields: Record<string, Field> | null;
    values: Record<string, string | null>;
};

export default function GatewayEdit({ gateway, fields }: Props) {
    const shipped = gateway.driver_status === 'shipped';

    return (
        <>
            <Head title={`${gateway.name} — Gateways`} />

            <div className="mb-6">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={gatewaysIndex()}>
                        <ArrowLeft className="size-4" />
                        Back to gateways
                    </Link>
                </Button>
            </div>

            <Heading
                title={gateway.name}
                description={gateway.description ?? ''}
            />

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    {fields === null ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>No editable fields yet</CardTitle>
                                <CardDescription>
                                    The {gateway.name} driver hasn't been scaffolded yet. Its
                                    credential fields will appear here once the driver class is
                                    added to <code>app/Support/Billing/</code>.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    ) : (
                        <Card>
                            <Form
                                action={`/admin/gateways/${gateway.id}`}
                                method="patch"
                                disableWhileProcessing
                                resetOnSuccess={false}
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <CardHeader>
                                            <CardTitle>Credentials</CardTitle>
                                            <CardDescription>
                                                Secrets are encrypted at rest. Saving applies on
                                                the next request — no redeploy needed.
                                            </CardDescription>
                                        </CardHeader>
                                        <CardContent className="grid gap-5">
                                            {Object.values(fields).map((field) => (
                                                <FieldRow
                                                    key={field.key}
                                                    field={field}
                                                    error={errors[field.key]}
                                                    plannedNotice={
                                                        !shipped &&
                                                        field.key ===
                                                            `${gateway.id.toUpperCase()}_ENABLED`
                                                    }
                                                />
                                            ))}
                                        </CardContent>
                                        <CardFooter>
                                            <Button type="submit" disabled={processing}>
                                                {processing ? (
                                                    <Spinner className="size-4" />
                                                ) : (
                                                    <Save className="size-4" />
                                                )}
                                                Save credentials
                                            </Button>
                                        </CardFooter>
                                    </>
                                )}
                            </Form>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <SidebarRow label="Driver">
                                {shipped ? (
                                    <Badge className="gap-1 bg-emerald-600 hover:bg-emerald-600">
                                        <CheckCircle2 className="size-3" /> Shipped
                                    </Badge>
                                ) : (
                                    <Badge variant="outline" className="gap-1">
                                        <CircleDashed className="size-3" /> Planned
                                    </Badge>
                                )}
                            </SidebarRow>
                            <SidebarRow label="Enabled">
                                {gateway.enabled ? 'Yes' : 'No'}
                            </SidebarRow>
                            <SidebarRow label="Configured">
                                {gateway.configured ? 'Yes' : 'No'}
                            </SidebarRow>
                            <SidebarRow label="Active subscriptions">
                                {gateway.active_subscriptions}
                            </SidebarRow>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Regions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-1">
                                {gateway.regions.map((r) => (
                                    <Badge key={r} variant="outline" className="text-xs">
                                        {r}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Capabilities</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-1">
                                {gateway.capabilities.map((c) => (
                                    <Badge key={c} variant="secondary" className="text-xs">
                                        {c}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {gateway.documentation_url ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Documentation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <a
                                    href={gateway.documentation_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-sm hover:underline"
                                >
                                    {gateway.documentation_url}
                                    <ExternalLink className="size-3" />
                                </a>
                            </CardContent>
                        </Card>
                    ) : null}
                </div>
            </div>
        </>
    );
}

function SidebarRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span>{children}</span>
        </div>
    );
}

function FieldRow({
    field,
    error,
    plannedNotice,
}: {
    field: Field;
    error?: string;
    plannedNotice?: boolean;
}) {
    const [revealed, setRevealed] = useState(false);
    const [value, setValue] = useState<string>(field.value ?? '');

    const inputId = `gw-${field.key}`;
    const isSelect = field.type === 'select' && field.options !== null;
    const isBool = field.type === 'bool';
    const isSecret = field.is_secret;

    if (isBool) {
        return (
            <div className="grid gap-2">
                <div className="flex items-center justify-between gap-4">
                    <Label htmlFor={inputId} className="flex-1">
                        {field.label}
                    </Label>
                    <input
                        type="hidden"
                        name={field.key}
                        value={value === '1' || value === 'true' ? '1' : '0'}
                    />
                    <Switch
                        id={inputId}
                        checked={value === '1' || value === 'true'}
                        onCheckedChange={(checked) => setValue(checked ? '1' : '0')}
                        disabled={plannedNotice}
                    />
                </div>
                {plannedNotice ? (
                    <p className="text-xs text-amber-700 dark:text-amber-300">
                        Locked until the driver ships.
                    </p>
                ) : null}
                {field.help ? (
                    <p className="text-xs text-muted-foreground">{field.help}</p>
                ) : null}
                <InputError message={error} />
            </div>
        );
    }

    if (isSelect) {
        return (
            <div className="grid gap-2">
                <Label htmlFor={inputId}>{field.label}</Label>
                <Select value={value || ''} onValueChange={setValue}>
                    <SelectTrigger id={inputId} className="w-full">
                        <SelectValue placeholder="Select…" />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(field.options ?? {}).map(([v, label]) => (
                            <SelectItem key={v || '__none__'} value={v || ' '}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name={field.key} value={value === ' ' ? '' : value} />
                {field.help ? (
                    <p className="text-xs text-muted-foreground">{field.help}</p>
                ) : null}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor={inputId}>{field.label}</Label>
            <div className="relative">
                <Input
                    id={inputId}
                    name={field.key}
                    type={
                        isSecret && !revealed
                            ? 'password'
                            : field.type === 'int'
                              ? 'number'
                              : 'text'
                    }
                    value={value}
                    placeholder={
                        isSecret && field.has_value && value === SECRET_MASK
                            ? 'Leave masked to keep current value'
                            : undefined
                    }
                    onFocus={() => {
                        if (isSecret && value === SECRET_MASK) {
setValue('');
}
                    }}
                    onChange={(e) => setValue(e.target.value)}
                    autoComplete={isSecret ? 'new-password' : 'off'}
                    className={isSecret ? 'pr-10' : undefined}
                />
                {isSecret ? (
                    <button
                        type="button"
                        onClick={() => setRevealed((r) => !r)}
                        className="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                        aria-label={revealed ? 'Hide value' : 'Show value'}
                        tabIndex={-1}
                    >
                        {revealed ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                    </button>
                ) : null}
            </div>
            <p className="font-mono text-xs text-muted-foreground">{field.key}</p>
            {field.help ? (
                <p className="text-xs text-muted-foreground">{field.help}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
