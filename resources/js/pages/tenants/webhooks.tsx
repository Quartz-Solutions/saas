import { Form, Head, usePage } from '@inertiajs/react';
import { Copy, MoreHorizontal, Plus, Webhook } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import WebhooksController from '@/actions/App/Http/Controllers/Webhooks/WebhooksController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/utils';
import tenantRoutes from '@/routes/tenants';

type WebhookEndpoint = {
    id: number;
    url: string;
    description: string | null;
    events: string[];
    is_active: boolean;
    failure_count: number;
    last_delivery_at: string | null;
    disabled_at: string | null;
    created_at: string;
};

type Delivery = {
    id: number;
    outbound_webhook_id: number;
    event_type: string;
    status: string;
    response_code: number | null;
    attempt: number;
    delivered_at: string | null;
    failed_at: string | null;
    created_at: string;
};

type Props = {
    endpoints: WebhookEndpoint[];
    deliveries: Delivery[];
    available_events: Record<string, string>;
};

type PageProps = {
    webhook_secret?: { id: number; plain_text: string };
};

export default function WebhooksIndex({ endpoints, deliveries, available_events }: Props) {
    const { props } = usePage<{ currentTenant: { slug: string } | null } & PageProps>();
    const tenantSlug = props.currentTenant?.slug ?? '';

    const [createOpen, setCreateOpen] = useState(false);
    const [editing, setEditing] = useState<WebhookEndpoint | null>(null);
    const [deleting, setDeleting] = useState<WebhookEndpoint | null>(null);
    const [revealed, setRevealed] = useState<{ id: number; plain_text: string } | null>(null);

    useEffect(() => {
        if (props.webhook_secret) {
            setRevealed(props.webhook_secret);
            setCreateOpen(false);
        }
    }, [props.webhook_secret]);

    const copy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Copied to clipboard');
        } catch {
            toast.error('Copy failed, please copy manually.');
        }
    };

    return (
        <>
            <Head title="Webhooks" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Outbound webhooks"
                        description="POST signed event payloads to your own URLs. Each request includes an X-Webhook-Signature header (HMAC-SHA256 of the body)."
                    />
                    <Button onClick={() => setCreateOpen(true)} data-test="create-webhook-button">
                        <Plus />
                        New endpoint
                    </Button>
                </div>

                {endpoints.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
                        No webhook endpoints yet.
                    </div>
                ) : (
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>URL</TableHead>
                                    <TableHead>Events</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Last delivery</TableHead>
                                    <TableHead className="w-px text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {endpoints.map((row) => (
                                    <TableRow key={row.id} data-test={`webhook-row-${row.id}`}>
                                        <TableCell className="font-mono text-xs">
                                            <div className="flex items-center gap-2">
                                                <Webhook className="size-4 text-muted-foreground" />
                                                {row.url}
                                            </div>
                                            {row.description && (
                                                <div className="text-xs text-muted-foreground">{row.description}</div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {row.events.map((e) => (
                                                    <Badge key={e} variant="secondary" className="font-mono text-[10px]">
                                                        {e}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {row.is_active ? (
                                                <Badge>Active</Badge>
                                            ) : (
                                                <Badge variant="outline">Disabled</Badge>
                                            )}
                                            {row.failure_count > 0 && (
                                                <span className="ml-2 text-xs text-red-600">
                                                    {row.failure_count} fails
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {row.last_delivery_at
                                                ? formatDateTime(row.last_delivery_at)
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-8">
                                                        <MoreHorizontal />
                                                        <span className="sr-only">Open actions</span>
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => setEditing(row)}>
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <RotateSecretItem row={row} tenantSlug={tenantSlug} />
                                                    <TestFireItem row={row} tenantSlug={tenantSlug} />
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem
                                                        variant="destructive"
                                                        onSelect={() => setDeleting(row)}
                                                    >
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {deliveries.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-semibold">Recent deliveries</h2>
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Event</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>HTTP</TableHead>
                                        <TableHead>Attempt</TableHead>
                                        <TableHead>When</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {deliveries.map((d) => (
                                        <TableRow key={d.id}>
                                            <TableCell className="font-mono text-xs">{d.event_type}</TableCell>
                                            <TableCell>
                                                <Badge variant={d.status === 'succeeded' ? 'default' : 'outline'}>
                                                    {d.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {d.response_code ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">{d.attempt}</TableCell>
                                            <TableCell className="font-mono text-xs text-muted-foreground">
                                                {formatDateTime(d.delivered_at ?? d.failed_at ?? d.created_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </section>
                )}
            </div>

            <CreateWebhookDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                tenantSlug={tenantSlug}
                availableEvents={available_events}
            />

            <EditWebhookDialog
                endpoint={editing}
                onClose={() => setEditing(null)}
                tenantSlug={tenantSlug}
                availableEvents={available_events}
            />

            <DeleteWebhookDialog
                endpoint={deleting}
                onClose={() => setDeleting(null)}
                tenantSlug={tenantSlug}
            />

            <RevealSecretDialog
                secret={revealed}
                onClose={() => setRevealed(null)}
                onCopy={copy}
            />
        </>
    );
}

function CreateWebhookDialog({
    open,
    onOpenChange,
    tenantSlug,
    availableEvents,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    tenantSlug: string;
    availableEvents: Record<string, string>;
}) {
    const [selected, setSelected] = useState<Set<string>>(new Set());

    const toggle = (key: string) => {
        setSelected((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
next.delete(key);
} else {
next.add(key);
}

            return next;
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => {
 onOpenChange(v);

 if (!v) {
setSelected(new Set());
} 
}}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>New webhook endpoint</DialogTitle>
                    <DialogDescription>
                        Pick the events you want to receive. We will sign each request with an HMAC-SHA256 signature.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...WebhooksController.store.form({ tenantSlug })}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="webhook-url">URL</Label>
                                <Input
                                    id="webhook-url"
                                    name="url"
                                    type="url"
                                    required
                                    placeholder="https://api.example.com/webhooks"
                                    data-test="webhook-url-input"
                                />
                                <InputError message={errors.url} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="webhook-description">Description (optional)</Label>
                                <Input
                                    id="webhook-description"
                                    name="description"
                                    maxLength={255}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Events</Label>
                                <div className="grid gap-1 rounded-md border p-3">
                                    {Object.entries(availableEvents).map(([key, desc]) => (
                                        <label
                                            key={key}
                                            className="flex items-start gap-3 rounded-sm px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                                        >
                                            <Checkbox
                                                checked={selected.has(key)}
                                                onCheckedChange={() => toggle(key)}
                                                className="mt-0.5"
                                                data-test={`event-${key}`}
                                            />
                                            <div className="flex-1">
                                                <code className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">
                                                    {key}
                                                </code>
                                                <p className="text-xs text-muted-foreground mt-1">{desc}</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                                {[...selected].map((k) => (
                                    <input key={k} type="hidden" name="events[]" value={k} />
                                ))}
                                <InputError message={errors.events} />
                            </div>
                            <div className="flex items-center justify-between rounded-md border px-3 py-2">
                                <Label htmlFor="webhook-active" className="cursor-pointer">
                                    Active
                                </Label>
                                <input type="hidden" name="is_active" value="0" />
                                <Switch id="webhook-active" name="is_active" value="1" defaultChecked />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="submit"
                                    disabled={processing || selected.size === 0}
                                    data-test="create-webhook-submit"
                                >
                                    {processing && <Spinner />}
                                    Create endpoint
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditWebhookDialog({
    endpoint,
    onClose,
    tenantSlug,
    availableEvents,
}: {
    endpoint: WebhookEndpoint | null;
    onClose: () => void;
    tenantSlug: string;
    availableEvents: Record<string, string>;
}) {
    const [selected, setSelected] = useState<Set<string>>(new Set());

    useEffect(() => {
        if (endpoint) {
setSelected(new Set(endpoint.events));
}
    }, [endpoint]);

    const toggle = (key: string) => {
        setSelected((prev) => {
            const next = new Set(prev);

            if (next.has(key)) {
next.delete(key);
} else {
next.add(key);
}

            return next;
        });
    };

    return (
        <Dialog open={endpoint !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Edit endpoint</DialogTitle>
                    <DialogDescription>
                        Adjust URL, subscribed events, or enable/disable the endpoint.
                    </DialogDescription>
                </DialogHeader>
                {endpoint && (
                    <Form
                        {...WebhooksController.update.form({ tenantSlug, webhook: endpoint.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-webhook-url">URL</Label>
                                    <Input
                                        id="edit-webhook-url"
                                        name="url"
                                        type="url"
                                        required
                                        defaultValue={endpoint.url}
                                    />
                                    <InputError message={errors.url} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="edit-webhook-description">Description</Label>
                                    <Input
                                        id="edit-webhook-description"
                                        name="description"
                                        defaultValue={endpoint.description ?? ''}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Events</Label>
                                    <div className="grid gap-1 rounded-md border p-3">
                                        {Object.entries(availableEvents).map(([key, desc]) => (
                                            <label
                                                key={key}
                                                className="flex items-start gap-3 rounded-sm px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                                            >
                                                <Checkbox
                                                    checked={selected.has(key)}
                                                    onCheckedChange={() => toggle(key)}
                                                    className="mt-0.5"
                                                />
                                                <div className="flex-1">
                                                    <code className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">
                                                        {key}
                                                    </code>
                                                    <p className="text-xs text-muted-foreground mt-1">{desc}</p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                    {[...selected].map((k) => (
                                        <input key={k} type="hidden" name="events[]" value={k} />
                                    ))}
                                    <InputError message={errors.events} />
                                </div>
                                <div className="flex items-center justify-between rounded-md border px-3 py-2">
                                    <Label htmlFor="edit-webhook-active">Active</Label>
                                    <input type="hidden" name="is_active" value="0" />
                                    <Switch
                                        id="edit-webhook-active"
                                        name="is_active"
                                        value="1"
                                        defaultChecked={endpoint.is_active}
                                    />
                                </div>
                                <DialogFooter className="gap-2">
                                    <Button type="button" variant="secondary" onClick={onClose}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Save changes
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DeleteWebhookDialog({
    endpoint,
    onClose,
    tenantSlug,
}: {
    endpoint: WebhookEndpoint | null;
    onClose: () => void;
    tenantSlug: string;
}) {
    return (
        <AlertDialog open={endpoint !== null} onOpenChange={(o) => !o && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete endpoint?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Future events will no longer be sent to this URL. Past delivery
                        history will also be removed.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {endpoint && (
                    <Form
                        {...WebhooksController.destroy.form({ tenantSlug, webhook: endpoint.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button type="submit" variant="destructive" disabled={processing}>
                                    {processing && <Spinner />}
                                    Delete endpoint
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RevealSecretDialog({
    secret,
    onClose,
    onCopy,
}: {
    secret: { id: number; plain_text: string } | null;
    onClose: () => void;
    onCopy: (text: string) => void;
}) {
    return (
        <Dialog open={secret !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Your webhook signing secret</DialogTitle>
                    <DialogDescription>
                        Store this securely. It will not be shown again. Verify incoming
                        requests by recomputing HMAC-SHA256(body, secret) and comparing
                        to the X-Webhook-Signature header.
                    </DialogDescription>
                </DialogHeader>
                {secret && (
                    <div className="space-y-3">
                        <div className="rounded-md border bg-muted p-3 font-mono text-sm break-all" data-test="revealed-webhook-secret">
                            {secret.plain_text}
                        </div>
                        <Button type="button" variant="outline" onClick={() => onCopy(secret.plain_text)}>
                            <Copy />
                            Copy to clipboard
                        </Button>
                    </div>
                )}
                <DialogFooter>
                    <Button type="button" onClick={onClose}>
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RotateSecretItem({ row, tenantSlug }: { row: WebhookEndpoint; tenantSlug: string }) {
    return (
        <Form
            {...WebhooksController.rotateSecret.form({ tenantSlug, webhook: row.id })}
            options={{ preserveScroll: true }}
        >
            {() => (
                <DropdownMenuItem asChild>
                    <button type="submit" className="w-full text-left">
                        Rotate secret
                    </button>
                </DropdownMenuItem>
            )}
        </Form>
    );
}

function TestFireItem({ row, tenantSlug }: { row: WebhookEndpoint; tenantSlug: string }) {
    return (
        <Form
            {...WebhooksController.testFire.form({ tenantSlug, webhook: row.id })}
            options={{ preserveScroll: true }}
        >
            {() => (
                <DropdownMenuItem asChild>
                    <button type="submit" className="w-full text-left">
                        Send test event
                    </button>
                </DropdownMenuItem>
            )}
        </Form>
    );
}

WebhooksIndex.layout = ({
    currentTenant,
}: {
    currentTenant: { slug: string; name: string } | null;
}) => {
    const slug = currentTenant?.slug ?? '';

    return {
        breadcrumbs: [
            { title: currentTenant?.name ?? 'Tenant', href: tenantRoutes.dashboard({ tenantSlug: slug }) },
            { title: 'Webhooks', href: tenantRoutes.webhooks.index({ tenantSlug: slug }) },
        ],
    };
};
