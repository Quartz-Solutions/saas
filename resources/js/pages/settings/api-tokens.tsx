import { Form, Head, usePage } from '@inertiajs/react';
import { Copy, KeyRound, MoreHorizontal, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ApiTokensController from '@/actions/App/Http/Controllers/ApiTokens/ApiTokensController';
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
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import apiTokensRoutes from '@/routes/api-tokens';

type Ability = {
    key: string;
    label: string;
    description: string;
    group: 'read' | 'write' | 'admin';
};

type TokenRow = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

type PlainTextToken = {
    id: number;
    name: string;
    plain_text: string;
};

type Props = {
    tokens: TokenRow[];
    abilities: Ability[];
};

type PageProps = {
    plain_text_token?: PlainTextToken;
};

export default function ApiTokensIndex({ tokens, abilities }: Props) {
    const { props } = usePage<PageProps>();
    const [createOpen, setCreateOpen] = useState(false);
    const [revoking, setRevoking] = useState<TokenRow | null>(null);
    const [revealed, setRevealed] = useState<PlainTextToken | null>(null);

    // When the controller flashes a plain-text token, surface the modal.
    useEffect(() => {
        if (props.plain_text_token) {
            setRevealed(props.plain_text_token);
            setCreateOpen(false);
        }
    }, [props.plain_text_token]);

    const copy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success('Copied to clipboard');
        } catch {
            toast.error('Could not copy. Please copy manually.');
        }
    };

    return (
        <>
            <Head title="API tokens" />

            <div className="space-y-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        variant="small"
                        title="API tokens"
                        description="Personal access tokens authenticate API calls to /api/v1/*. Treat them like passwords — anyone with a token can act as you."
                    />
                    <Button onClick={() => setCreateOpen(true)} data-test="create-token-button">
                        <Plus />
                        New token
                    </Button>
                </div>

                {tokens.length === 0 ? (
                    <div className="rounded-md border bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground">
                        You haven&apos;t minted any API tokens yet.
                    </div>
                ) : (
                    <ul className="divide-y divide-border rounded-md border" data-test="tokens-list">
                        {tokens.map((row) => (
                            <li key={row.id} className="flex items-center gap-4 p-4">
                                <KeyRound className="size-5 text-muted-foreground" />
                                <div className="flex-1 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                        <span>{row.name}</span>
                                        {row.abilities.length > 0 && row.abilities.map((a) => (
                                            <Badge key={a} variant="secondary" className="font-mono text-[10px]">
                                                {a}
                                            </Badge>
                                        ))}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Created {formatDateTime(row.created_at)} ·{' '}
                                        {row.last_used_at
                                            ? `Last used ${formatDateTime(row.last_used_at)}`
                                            : 'Never used'}
                                        {row.expires_at && ` · Expires ${formatDateTime(row.expires_at)}`}
                                    </div>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="size-8" data-test={`token-actions-${row.id}`}>
                                            <MoreHorizontal />
                                            <span className="sr-only">Open actions</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            variant="destructive"
                                            onSelect={() => setRevoking(row)}
                                        >
                                            Revoke
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <CreateTokenDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                abilities={abilities}
            />

            <RevokeTokenDialog
                token={revoking}
                onClose={() => setRevoking(null)}
            />

            <RevealTokenDialog
                token={revealed}
                onClose={() => setRevealed(null)}
                onCopy={copy}
            />
        </>
    );
}

function CreateTokenDialog({
    open,
    onOpenChange,
    abilities,
}: {
    open: boolean;
    onOpenChange: (v: boolean) => void;
    abilities: Ability[];
}) {
    const [selected, setSelected] = useState<Set<string>>(new Set(['profile:read']));

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
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Create API token</DialogTitle>
                    <DialogDescription>
                        Give the token a memorable name and pick which abilities it
                        grants. You can only view the plain-text token once.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...ApiTokensController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setSelected(new Set(['profile:read']))}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="token-name">Name</Label>
                                <Input
                                    id="token-name"
                                    name="name"
                                    placeholder="e.g. CI deploy bot"
                                    required
                                    autoFocus
                                    data-test="create-token-name"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Abilities</Label>
                                <div className="grid gap-2 rounded-md border p-3 max-h-72 overflow-y-auto">
                                    {abilities.map((a) => (
                                        <label
                                            key={a.key}
                                            className="flex items-start gap-3 rounded-sm px-2 py-1.5 text-sm hover:bg-muted/50 cursor-pointer"
                                        >
                                            <Checkbox
                                                checked={selected.has(a.key)}
                                                onCheckedChange={() => toggle(a.key)}
                                                className="mt-0.5"
                                                data-test={`ability-${a.key}`}
                                            />
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 font-medium">
                                                    <code className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-mono">
                                                        {a.key}
                                                    </code>
                                                    <span>{a.label}</span>
                                                </div>
                                                <p className="text-xs text-muted-foreground">{a.description}</p>
                                            </div>
                                        </label>
                                    ))}
                                </div>
                                {[...selected].map((key) => (
                                    <input key={key} type="hidden" name="abilities[]" value={key} />
                                ))}
                                <InputError message={errors.abilities} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="token-expires">Expires in (days, optional)</Label>
                                <Input
                                    id="token-expires"
                                    name="expires_in_days"
                                    type="number"
                                    min={1}
                                    max={3650}
                                    placeholder="Never"
                                />
                                <InputError message={errors.expires_in_days} />
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
                                    data-test="create-token-submit"
                                >
                                    {processing && <Spinner />}
                                    Create token
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function RevealTokenDialog({
    token,
    onClose,
    onCopy,
}: {
    token: PlainTextToken | null;
    onClose: () => void;
    onCopy: (text: string) => void;
}) {
    return (
        <Dialog open={token !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Your new API token</DialogTitle>
                    <DialogDescription>
                        Copy and store this token in a secure place — we won&apos;t show it again.
                    </DialogDescription>
                </DialogHeader>
                {token && (
                    <div className="space-y-3">
                        <div className="rounded-md border bg-muted p-3 font-mono text-sm break-all" data-test="revealed-token">
                            {token.plain_text}
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onCopy(token.plain_text)}
                            data-test="copy-token-button"
                        >
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

function RevokeTokenDialog({
    token,
    onClose,
}: {
    token: TokenRow | null;
    onClose: () => void;
}) {
    return (
        <AlertDialog open={token !== null} onOpenChange={(open) => !open && onClose()}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Revoke this token?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Any service using <span className="font-medium">{token?.name}</span>{' '}
                        will immediately lose access. This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {token && (
                    <Form
                        {...ApiTokensController.destroy.form({ token: token.id })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                    >
                        {({ processing }) => (
                            <AlertDialogFooter>
                                <Button type="button" variant="secondary" onClick={onClose}>
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    data-test="confirm-revoke-token"
                                >
                                    {processing && <Spinner />}
                                    Revoke token
                                </Button>
                            </AlertDialogFooter>
                        )}
                    </Form>
                )}
            </AlertDialogContent>
        </AlertDialog>
    );
}

ApiTokensIndex.layout = {
    breadcrumbs: [
        { title: 'API tokens', href: apiTokensRoutes.index() },
    ],
};
