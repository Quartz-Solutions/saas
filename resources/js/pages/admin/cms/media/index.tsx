import { Head, router } from '@inertiajs/react';
import { Check, Copy, Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { destroy as mediaDestroy, index as mediaIndex, store as mediaStore, update as mediaUpdate } from '@/routes/admin/cms/media';

type Asset = {
    id: number;
    filename: string;
    mime_type: string;
    size_bytes: number;
    width: number | null;
    height: number | null;
    url: string;
    metadata: { alt?: string; focal_x?: number; focal_y?: number };
    created_at: string | null;
};

type Props = {
    assets: {
        data: Asset[];
        meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
    search: string;
};

function humanSize(bytes: number): string {
    if (bytes < 1024) {
return `${bytes} B`;
}

    if (bytes < 1024 * 1024) {
return `${(bytes / 1024).toFixed(1)} KB`;
}

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export default function CmsMediaIndex({ assets, search: initialSearch }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [search, setSearch] = useState(initialSearch);
    const [editing, setEditing] = useState<Asset | null>(null);
    const [deleting, setDeleting] = useState<Asset | null>(null);

    async function onFiles(e: ChangeEvent<HTMLInputElement>) {
        const files = e.target.files;

        if (!files || files.length === 0) {
return;
}

        setUploading(true);

        try {
            for (const file of Array.from(files)) {
                const form = new FormData();
                form.append('file', file);
                const tokenEl = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
                const res = await fetch(mediaStore().url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': tokenEl?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });

                if (!res.ok) {
                    const data: { message?: string } = await res.json().catch(() => ({}));

                    throw new Error(data.message || `Upload failed (${res.status})`);
                }
            }

            router.reload({ only: ['assets'] });
            toast.success(`Uploaded ${files.length} file${files.length === 1 ? '' : 's'}.`);
        } catch (e: unknown) {
            toast.error(e instanceof Error ? e.message : 'Upload failed.');
        } finally {
            setUploading(false);

            if (fileRef.current) {
fileRef.current.value = '';
}
        }
    }

    function applySearch() {
        router.get(mediaIndex().url, search ? { search } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['assets', 'search'],
        });
    }

    function copyUrl(url: string) {
        navigator.clipboard?.writeText(url);
        toast.success('URL copied');
    }

    function saveMetadata() {
        if (!editing) {
return;
}

        const { id } = editing;
        router.patch(mediaUpdate({ media_asset: id }).url, {
            alt: editing.metadata.alt ?? '',
            focal_x: editing.metadata.focal_x ?? 0.5,
            focal_y: editing.metadata.focal_y ?? 0.5,
        }, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    }

    function confirmDelete() {
        if (!deleting) {
return;
}

        router.delete(mediaDestroy({ media_asset: deleting.id }).url, {
            preserveScroll: true,
            onFinish: () => setDeleting(null),
        });
    }

    return (
        <>
            <Head title="Media — CMS" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Media library"
                        description="Images used across CMS pages and globals. Click an item to copy its URL."
                    />
                    <div className="flex items-center gap-2">
                        <input
                            ref={fileRef}
                            type="file"
                            multiple
                            accept="image/*"
                            className="hidden"
                            onChange={onFiles}
                            data-test="media-file-input"
                        />
                        <Button onClick={() => fileRef.current?.click()} disabled={uploading}>
                            <Upload className="mr-1 size-4" />
                            {uploading ? 'Uploading…' : 'Upload'}
                        </Button>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Input
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                        placeholder="Search by filename…"
                        className="max-w-sm"
                    />
                    <Button variant="outline" onClick={applySearch}>
                        Search
                    </Button>
                </div>

                {assets.data.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-16 text-center text-sm text-muted-foreground">
                        No media yet. Click <strong>Upload</strong> to add files.
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                        {assets.data.map((asset) => (
                            <Card
                                key={asset.id}
                                className="group cursor-pointer overflow-hidden border-border/60 transition-shadow hover:shadow-md"
                                onClick={() => setEditing(asset)}
                                data-test={`media-tile-${asset.id}`}
                            >
                                <div className="aspect-square w-full overflow-hidden bg-muted">
                                    {asset.mime_type.startsWith('image/') ? (
                                        <img src={asset.url} alt={asset.metadata.alt ?? asset.filename} className="size-full object-cover" />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                                            {asset.mime_type}
                                        </div>
                                    )}
                                </div>
                                <CardContent className="space-y-1 p-2">
                                    <div className="truncate text-xs font-medium" title={asset.filename}>
                                        {asset.filename}
                                    </div>
                                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                                        <span>
                                            {asset.width && asset.height ? `${asset.width}×${asset.height}` : '—'}
                                        </span>
                                        <span>{humanSize(asset.size_bytes)}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <AlertDialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <AlertDialogContent className="max-w-xl">
                    <AlertDialogHeader>
                        <AlertDialogTitle>Edit media</AlertDialogTitle>
                        <AlertDialogDescription>
                            Update alt text and focal point. Alt text is used by screen readers and as image fallback.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    {editing && (
                        <div className="space-y-4">
                            <div className="aspect-video w-full overflow-hidden rounded-md bg-muted">
                                <img src={editing.url} alt={editing.metadata.alt ?? editing.filename} className="size-full object-contain" />
                            </div>
                            <div className="flex items-center gap-2">
                                <Input readOnly value={editing.url} className="font-mono text-xs" />
                                <Button type="button" variant="outline" size="icon" onClick={() => copyUrl(editing.url)} aria-label="Copy URL">
                                    <Copy className="size-4" />
                                </Button>
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="alt">Alt text</Label>
                                <Textarea
                                    id="alt"
                                    value={editing.metadata.alt ?? ''}
                                    onChange={(e) => setEditing({ ...editing, metadata: { ...editing.metadata, alt: e.target.value } })}
                                    rows={2}
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1">
                                    <Label htmlFor="fx">Focal X (0–1)</Label>
                                    <Input
                                        id="fx"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1"
                                        value={editing.metadata.focal_x ?? 0.5}
                                        onChange={(e) =>
                                            setEditing({
                                                ...editing,
                                                metadata: { ...editing.metadata, focal_x: Number(e.target.value) },
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="fy">Focal Y (0–1)</Label>
                                    <Input
                                        id="fy"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="1"
                                        value={editing.metadata.focal_y ?? 0.5}
                                        onChange={(e) =>
                                            setEditing({
                                                ...editing,
                                                metadata: { ...editing.metadata, focal_y: Number(e.target.value) },
                                            })
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                    <AlertDialogFooter>
                        <Button type="button" variant="destructive" onClick={() => editing && setDeleting(editing)}>
                            <Trash2 className="mr-1 size-4" />
                            Delete
                        </Button>
                        <div className="flex-1" />
                        <Button type="button" variant="ghost" onClick={() => setEditing(null)}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={saveMetadata}>
                            <Check className="mr-1 size-4" />
                            Save
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete this file?</AlertDialogTitle>
                        <AlertDialogDescription>
                            The underlying file is removed from storage. References on existing pages will 404.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <Button type="button" variant="ghost" onClick={() => setDeleting(null)}>
                            Cancel
                        </Button>
                        <Button type="button" variant="destructive" onClick={confirmDelete}>
                            Delete
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
