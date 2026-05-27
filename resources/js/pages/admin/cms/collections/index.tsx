import { Head, router } from '@inertiajs/react';
import { MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as collectionDestroy,
    store as collectionStore,
    update as collectionUpdate,
} from '@/routes/admin/cms/collections';

type FieldType = 'text' | 'textarea' | 'url' | 'switch' | 'number';

type Field = {
    key: string;
    label: string;
    type: FieldType;
    required?: boolean;
};

type Item = Record<string, unknown> & { id: number; is_active?: boolean };

type Props = {
    type: 'features' | 'testimonials' | 'faqs' | 'logos';
    label: string;
    description: string;
    items: Item[];
    fields: Field[];
};

function defaultRecord(fields: Field[]): Record<string, unknown> {
    const r: Record<string, unknown> = {};
    fields.forEach((f) => {
        r[f.key] = f.type === 'switch' ? true : f.type === 'number' ? 0 : '';
    });

    return r;
}

function summarize(item: Item, type: Props['type']): string {
    if (type === 'features') {
return String(item.title ?? '');
}

    if (type === 'testimonials') {
return `${String(item.author_name ?? '')} — ${String(item.company ?? '')}`;
}

    if (type === 'faqs') {
return String(item.question ?? '');
}

    if (type === 'logos') {
return String(item.name ?? '');
}

    return '';
}

export default function CollectionsIndex({ type, label, description, items, fields }: Props) {
    const [editing, setEditing] = useState<Item | null>(null);
    const [draft, setDraft] = useState<Record<string, unknown>>(defaultRecord(fields));

    function openNew() {
        setEditing(null);
        setDraft(defaultRecord(fields));
    }

    function openEdit(item: Item) {
        setEditing(item);
        setDraft({ ...item });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        const data = draft as unknown as Record<string, never>;

        if (editing) {
            router.patch(collectionUpdate({ type, id: editing.id }).url, data, {
                preserveScroll: true,
                onSuccess: () => setDraft(defaultRecord(fields)),
            });
        } else {
            router.post(collectionStore({ type }).url, data, {
                preserveScroll: true,
                onSuccess: () => setDraft(defaultRecord(fields)),
            });
        }
    }

    function remove(item: Item) {
        if (!confirm('Delete this item?')) {
return;
}

        router.delete(collectionDestroy({ type, id: item.id }).url, { preserveScroll: true });
    }

    const dialogOpen = editing !== null || (typeof window !== 'undefined' && (window as unknown as { __cmsNewOpen?: boolean }).__cmsNewOpen === true);

    const [newDialogOpen, setNewDialogOpen] = useState(false);

    function set(key: string, value: unknown) {
        setDraft((d) => ({ ...d, [key]: value }));
    }

    return (
        <>
            <Head title={`${label} — CMS`} />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title={label} description={description} />
                    <Button
                        onClick={() => {
                            openNew();
                            setNewDialogOpen(true);
                        }}
                    >
                        <Plus className="size-4" />
                        New
                    </Button>
                </div>

                {items.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        Nothing here yet. Click <strong>New</strong> to create the first item.
                    </div>
                ) : (
                    <div className="rounded-md border border-border/60">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Title</th>
                                    <th className="px-3 py-2 text-left font-medium">Status</th>
                                    <th className="px-3 py-2 text-left font-medium">Sort</th>
                                    <th className="w-px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="cursor-pointer border-t border-border/40 hover:bg-muted/30"
                                        onClick={() => openEdit(item)}
                                        data-test={`collection-row-${item.id}`}
                                    >
                                        <td className="px-3 py-2">{summarize(item, type)}</td>
                                        <td className="px-3 py-2">
                                            {item.is_active === false ? (
                                                <Badge variant="outline" className="text-muted-foreground">Inactive</Badge>
                                            ) : (
                                                <Badge variant="default">Active</Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                            {String(item.sort_order ?? 0)}
                                        </td>
                                        <td className="px-3 py-2 text-right" onClick={(e) => e.stopPropagation()}>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-7">
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem onSelect={() => openEdit(item)}>
                                                        Edit
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem variant="destructive" onSelect={() => remove(item)}>
                                                        <Trash2 className="size-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <Dialog
                open={editing !== null || newDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                        setNewDialogOpen(false);
                    }
                }}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit item' : 'New item'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        {fields.map((field) => (
                            <div key={field.key} className="space-y-1">
                                <Label htmlFor={field.key}>
                                    {field.label}
                                    {field.required && <span className="ml-1 text-destructive">*</span>}
                                </Label>
                                {field.type === 'textarea' && (
                                    <Textarea
                                        id={field.key}
                                        value={String(draft[field.key] ?? '')}
                                        onChange={(e) => set(field.key, e.target.value)}
                                        rows={3}
                                    />
                                )}
                                {field.type === 'switch' && (
                                    <Switch
                                        checked={Boolean(draft[field.key])}
                                        onCheckedChange={(checked) => set(field.key, checked)}
                                    />
                                )}
                                {field.type === 'number' && (
                                    <Input
                                        id={field.key}
                                        type="number"
                                        value={String(draft[field.key] ?? '')}
                                        onChange={(e) => set(field.key, e.target.value === '' ? null : Number(e.target.value))}
                                    />
                                )}
                                {(field.type === 'text' || field.type === 'url') && (
                                    <Input
                                        id={field.key}
                                        type={field.type === 'url' ? 'url' : 'text'}
                                        value={String(draft[field.key] ?? '')}
                                        onChange={(e) => set(field.key, e.target.value)}
                                        required={field.required}
                                    />
                                )}
                            </div>
                        ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => {
                                    setEditing(null);
                                    setNewDialogOpen(false);
                                }}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">{editing ? 'Save' : 'Create'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
