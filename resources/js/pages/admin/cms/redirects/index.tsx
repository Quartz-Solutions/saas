import { Head, router } from '@inertiajs/react';
import { ArrowRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/utils';
import {
    destroy as redirectsDestroy,
    from404 as convertFromNotFound,
    store as redirectsStore,
} from '@/routes/admin/cms/redirects';

type Redirect = {
    id: number;
    from_path: string;
    to_path: string;
    status_code: number;
    is_active: boolean;
    hits: number;
    last_hit_at: string | null;
    created_at: string | null;
};

type NotFoundEntry = {
    id: number;
    path: string;
    hits: number;
    referer: string | null;
    last_hit_at: string | null;
};

type Props = {
    redirects: Redirect[];
    notFoundLog: NotFoundEntry[];
};

const STATUS_CODES = [
    { value: '301', label: '301 (permanent)' },
    { value: '302', label: '302 (temporary)' },
    { value: '307', label: '307' },
    { value: '308', label: '308' },
];

export default function CmsRedirectsIndex({ redirects, notFoundLog }: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [fromPath, setFromPath] = useState('');
    const [toPath, setToPath] = useState('');
    const [statusCode, setStatusCode] = useState('301');

    function reset() {
        setFromPath('');
        setToPath('');
        setStatusCode('301');
    }

    function createSubmit(e: FormEvent) {
        e.preventDefault();
        router.post(
            redirectsStore().url,
            { from_path: fromPath, to_path: toPath, status_code: Number(statusCode) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    setCreateOpen(false);
                },
            },
        );
    }

    function destroy(r: Redirect) {
        if (!confirm(`Delete redirect ${r.from_path} → ${r.to_path}?`)) {
return;
}

        router.delete(redirectsDestroy({ redirect: r.id }).url, { preserveScroll: true });
    }

    function convert404(entry: NotFoundEntry) {
        const to = prompt(`Convert 404 ${entry.path} to which path?`);

        if (!to) {
return;
}

        router.post(
            convertFromNotFound({ id: entry.id }).url,
            { from_path: entry.path, to_path: to, status_code: 301 },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Redirects — CMS" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Redirects"
                        description="Map old paths to new ones. Each request is checked against this list before route resolution."
                    />
                    <Button onClick={() => setCreateOpen(true)}>
                        <Plus className="size-4" />
                        New redirect
                    </Button>
                </div>

                {redirects.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        No redirects yet. Click <strong>New redirect</strong> to add one.
                    </div>
                ) : (
                    <div className="rounded-md border border-border/60">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">From</th>
                                    <th className="px-3 py-2 text-left font-medium">To</th>
                                    <th className="px-3 py-2 text-left font-medium">Status</th>
                                    <th className="px-3 py-2 text-left font-medium">Hits</th>
                                    <th className="px-3 py-2 text-left font-medium">Last hit</th>
                                    <th className="w-px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {redirects.map((r) => (
                                    <tr key={r.id} className="border-t border-border/40">
                                        <td className="px-3 py-2 font-mono text-xs">{r.from_path}</td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            <ArrowRight className="mr-1 inline size-3 text-muted-foreground" />
                                            {r.to_path}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge variant="outline">{r.status_code}</Badge>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">{r.hits}</td>
                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                            {r.last_hit_at ? formatDateTime(r.last_hit_at) : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button variant="ghost" size="icon" onClick={() => destroy(r)} aria-label="Delete">
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Recent 404s</CardTitle>
                        <CardDescription>
                            Inbound requests that didn't match any route or page. Click an entry to convert it to a redirect.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {notFoundLog.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No 404s logged.</p>
                        ) : (
                            <div className="space-y-1">
                                {notFoundLog.map((entry) => (
                                    <button
                                        key={entry.id}
                                        type="button"
                                        onClick={() => convert404(entry)}
                                        className="flex w-full items-center justify-between gap-3 rounded px-2 py-1.5 text-left text-sm hover:bg-muted/50"
                                    >
                                        <span className="truncate font-mono text-xs">{entry.path}</span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {entry.hits} hit{entry.hits === 1 ? '' : 's'} · {entry.last_hit_at ? formatDateTime(entry.last_hit_at) : '—'}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New redirect</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={createSubmit} className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="from_path">From path</Label>
                            <Input
                                id="from_path"
                                value={fromPath}
                                onChange={(e) => setFromPath(e.target.value)}
                                placeholder="/old/path"
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="to_path">To path</Label>
                            <Input
                                id="to_path"
                                value={toPath}
                                onChange={(e) => setToPath(e.target.value)}
                                placeholder="/new/path or https://example.com/somewhere"
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="status_code">Status</Label>
                            <Select value={statusCode} onValueChange={setStatusCode}>
                                <SelectTrigger id="status_code" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_CODES.map((c) => (
                                        <SelectItem key={c.value} value={c.value}>
                                            {c.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setCreateOpen(false)}>
                                Cancel
                            </Button>
                            <Button type="submit">Create</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
