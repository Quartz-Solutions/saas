import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Plus, Save, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { store as formsStore, update as formsUpdate } from '@/actions/App/Http/Controllers/Admin/Cms/FormsController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { index as formsIndex } from '@/routes/admin/cms/forms';

type Field = {
    key: string;
    label: string;
    type: 'text' | 'email' | 'tel' | 'textarea' | 'select' | 'checkbox' | 'number' | 'url';
    required?: boolean;
    options?: string[];
};

type Form = {
    id?: number;
    slug: string;
    name: string;
    fields: Field[];
    success_message: string | null;
    notify_email: string | null;
    webhook_url: string | null;
    store_submissions: boolean;
    is_active: boolean;
};

type Props = {
    form: Form | null;
};

const FIELD_TYPES: Array<Field['type']> = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'number', 'url'];

const blankField = (): Field => ({ key: '', label: '', type: 'text', required: false });

export default function FormsEdit({ form: initial }: Props) {
    const isCreate = initial === null;
    const [name, setName] = useState(initial?.name ?? '');
    const [slug, setSlug] = useState(initial?.slug ?? '');
    const [fields, setFields] = useState<Field[]>(initial?.fields ?? [{ key: 'name', label: 'Name', type: 'text', required: true }, { key: 'email', label: 'Email', type: 'email', required: true }, { key: 'message', label: 'Message', type: 'textarea', required: true }]);
    const [successMessage, setSuccessMessage] = useState(initial?.success_message ?? '');
    const [notifyEmail, setNotifyEmail] = useState(initial?.notify_email ?? '');
    const [webhookUrl, setWebhookUrl] = useState(initial?.webhook_url ?? '');
    const [storeSubmissions, setStoreSubmissions] = useState(initial?.store_submissions ?? true);
    const [isActive, setIsActive] = useState(initial?.is_active ?? true);
    const [submitting, setSubmitting] = useState(false);

    function updateField(idx: number, next: Field) {
        const copy = fields.slice();
        copy[idx] = next;
        setFields(copy);
    }

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        const payload = {
            name,
            slug,
            fields,
            success_message: successMessage || null,
            notify_email: notifyEmail || null,
            webhook_url: webhookUrl || null,
            store_submissions: storeSubmissions,
            is_active: isActive,
        } as unknown as Record<string, never>;
        const opts = { preserveScroll: true, onFinish: () => setSubmitting(false) };

        if (isCreate) {
router.post(formsStore().url, payload, opts);
} else {
router.patch(formsUpdate({ form: initial!.id! }).url, payload, opts);
}
    }

    return (
        <>
            <Head title={isCreate ? 'New form' : `Edit: ${name}`} />

            <form onSubmit={onSubmit} className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={formsIndex().url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title={isCreate ? 'New form' : `Edit: ${name}`}
                            description="Define fields, success copy, and notification routing."
                        />
                    </div>
                    <Button type="submit" disabled={submitting}>
                        <Save className="mr-1 size-4" />
                        {submitting ? 'Saving…' : 'Save'}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>General</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="name">Name</Label>
                            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="slug">Slug (used by the block + POST URL)</Label>
                            <Input id="slug" value={slug} onChange={(e) => setSlug(e.target.value)} required />
                        </div>
                        <div className="space-y-1 md:col-span-2">
                            <Label htmlFor="success_message">Success message</Label>
                            <Textarea
                                id="success_message"
                                value={successMessage}
                                onChange={(e) => setSuccessMessage(e.target.value)}
                                rows={2}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Fields</CardTitle>
                        <CardDescription>Each entry is one input. Slugs become payload keys.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {fields.map((field, idx) => (
                            <div key={idx} className="grid items-end gap-2 rounded-md border border-border/60 p-3 md:grid-cols-12">
                                <div className="md:col-span-3 space-y-1">
                                    <Label>Key</Label>
                                    <Input
                                        value={field.key}
                                        onChange={(e) => updateField(idx, { ...field, key: e.target.value })}
                                        placeholder="email"
                                    />
                                </div>
                                <div className="md:col-span-4 space-y-1">
                                    <Label>Label</Label>
                                    <Input
                                        value={field.label}
                                        onChange={(e) => updateField(idx, { ...field, label: e.target.value })}
                                        placeholder="Email address"
                                    />
                                </div>
                                <div className="md:col-span-3 space-y-1">
                                    <Label>Type</Label>
                                    <Select value={field.type} onValueChange={(v) => updateField(idx, { ...field, type: v as Field['type'] })}>
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {FIELD_TYPES.map((t) => (
                                                <SelectItem key={t} value={t}>
                                                    {t}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center gap-2 md:col-span-1">
                                    <Switch
                                        checked={!!field.required}
                                        onCheckedChange={(c) => updateField(idx, { ...field, required: c })}
                                    />
                                </div>
                                <div className="flex justify-end md:col-span-1">
                                    <Button type="button" variant="ghost" size="icon" onClick={() => setFields(fields.filter((_, i) => i !== idx))} aria-label="Remove field">
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                        <Button type="button" variant="outline" onClick={() => setFields([...fields, blankField()])}>
                            <Plus className="mr-1 size-3" />
                            Add field
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Notifications & storage</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-1">
                            <Label htmlFor="notify_email">Notify email</Label>
                            <Input id="notify_email" type="email" value={notifyEmail} onChange={(e) => setNotifyEmail(e.target.value)} />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="webhook_url">Webhook URL</Label>
                            <Input id="webhook_url" type="url" value={webhookUrl} onChange={(e) => setWebhookUrl(e.target.value)} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch checked={storeSubmissions} onCheckedChange={setStoreSubmissions} />
                            <Label>Store submissions in inbox</Label>
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch checked={isActive} onCheckedChange={setIsActive} />
                            <Label>Active (visitors can submit)</Label>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
