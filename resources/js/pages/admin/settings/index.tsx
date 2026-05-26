import { Form, Head } from '@inertiajs/react';
import axios from 'axios';
import {
    Bug,
    CheckCircle2,
    Cloud,
    CreditCard,
    Eye,
    EyeOff,
    Globe,
    Hash,
    KeyRound,
    Mail,
    Plug,
    Save,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';

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

type Group = {
    key: string;
    label: string;
    description: string | null;
    icon: string | null;
    fields: Record<string, Field>;
};

type Props = {
    groups: Record<string, Group>;
};

const ICON_MAP: Record<string, LucideIcon> = {
    Globe,
    Mail,
    KeyRound,
    CreditCard,
    Bug,
    Hash,
    Cloud,
};

const GROUPS_WITH_TEST = new Set(['mail', 'stripe', 'sentry', 'slack', 'aws']);
const SECRET_MASK = '••••••••';

export default function AdminSettings({ groups }: Props) {
    const groupKeys = useMemo(() => Object.keys(groups), [groups]);
    const [active, setActive] = useState<string>(groupKeys[0] ?? 'app');

    return (
        <>
            <Head title="Settings" />
            <Heading
                title="Settings"
                description="Runtime configuration — saved values override .env values on the next request."
            />

            <Tabs value={active} onValueChange={setActive}>
                <TabsList className="mb-4 flex h-auto flex-wrap justify-start gap-1">
                    {groupKeys.map((key) => {
                        const group = groups[key];
                        const Icon = group.icon ? ICON_MAP[group.icon] : null;
                        return (
                            <TabsTrigger key={key} value={key} className="gap-2">
                                {Icon ? <Icon className="size-4" /> : null}
                                {group.label}
                            </TabsTrigger>
                        );
                    })}
                </TabsList>

                {groupKeys.map((key) => (
                    <TabsContent key={key} value={key}>
                        <GroupForm group={groups[key]} />
                    </TabsContent>
                ))}
            </Tabs>
        </>
    );
}

function GroupForm({ group }: { group: Group }) {
    const fields = useMemo(() => Object.values(group.fields), [group]);
    const canTest = GROUPS_WITH_TEST.has(group.key);
    const [testing, setTesting] = useState(false);

    const runTest = async () => {
        setTesting(true);
        try {
            const res = await axios.post<{ ok: boolean; message: string }>(
                `/admin/settings/${group.key}/test`,
            );
            if (res.data.ok) {
                toast.success(res.data.message, {
                    icon: <CheckCircle2 className="size-4 text-emerald-500" />,
                });
            } else {
                toast.error(res.data.message, {
                    icon: <XCircle className="size-4 text-red-500" />,
                });
            }
        } catch (e) {
            const message =
                axios.isAxiosError(e) && e.response?.data?.message
                    ? (e.response.data.message as string)
                    : 'Test failed — check server logs.';
            toast.error(message, { icon: <XCircle className="size-4 text-red-500" /> });
        } finally {
            setTesting(false);
        }
    };

    return (
        <Card>
            <Form
                {...SettingsController.update.form({ group: group.key })}
                disableWhileProcessing
                resetOnSuccess={false}
            >
                {({ processing, errors }) => (
                    <>
                        <CardHeader>
                            <CardTitle>{group.label}</CardTitle>
                            {group.description ? (
                                <CardDescription>{group.description}</CardDescription>
                            ) : null}
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            {fields.map((field) => (
                                <FieldRow
                                    key={field.key}
                                    field={field}
                                    error={errors[field.key]}
                                />
                            ))}
                        </CardContent>
                        <CardFooter className="flex flex-wrap items-center justify-between gap-2">
                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner className="size-4" /> : <Save className="size-4" />}
                                Save {group.label.toLowerCase()}
                            </Button>
                            {canTest ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={runTest}
                                    disabled={testing}
                                    data-test={`test-${group.key}`}
                                >
                                    {testing ? <Spinner className="size-4" /> : <Plug className="size-4" />}
                                    Test connection
                                </Button>
                            ) : null}
                        </CardFooter>
                    </>
                )}
            </Form>
        </Card>
    );
}

function FieldRow({ field, error }: { field: Field; error?: string }) {
    const [revealed, setRevealed] = useState(false);
    const [value, setValue] = useState<string>(field.value ?? '');

    const inputId = `setting-${field.key}`;
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
                    <input type="hidden" name={field.key} value={value ? '1' : '0'} />
                    <Switch
                        id={inputId}
                        checked={value === '1' || value === 'true'}
                        onCheckedChange={(checked) => setValue(checked ? '1' : '0')}
                    />
                </div>
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
                    type={isSecret && !revealed ? 'password' : field.type === 'int' ? 'number' : 'text'}
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
