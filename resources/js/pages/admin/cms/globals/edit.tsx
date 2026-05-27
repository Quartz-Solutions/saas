import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FooterColumnsEditor from '@/components/cms/admin/footer-columns-editor';
import type {FooterColumn} from '@/components/cms/admin/footer-columns-editor';
import MenuEditor from '@/components/cms/admin/menu-editor';
import type {MenuItem} from '@/components/cms/admin/menu-editor';
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
import { update as globalsUpdate, index as globalsIndex } from '@/routes/admin/cms/globals';

type Field = {
    key: string;
    label: string;
    type:
        | 'text'
        | 'textarea'
        | 'url'
        | 'email'
        | 'color'
        | 'image'
        | 'select'
        | 'switch'
        | 'number'
        | 'menu'
        | 'columns';
    options?: string[];
};

type Props = {
    global: {
        key: string;
        label: string;
        description: string;
        fields: Field[];
        payload: Record<string, unknown>;
    };
};

export default function CmsGlobalsEdit({ global }: Props) {
    const [payload, setPayload] = useState<Record<string, unknown>>(global.payload);
    const [submitting, setSubmitting] = useState(false);

    function set(key: string, value: unknown) {
        setPayload((p) => ({ ...p, [key]: value }));
    }

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        router.patch(
            globalsUpdate({ key: global.key }).url,
            { payload } as unknown as Record<string, never>,
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <>
            <Head title={`${global.label} — Globals`} />

            <form onSubmit={onSubmit} className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={globalsIndex().url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading title={global.label} description={global.description} />
                    </div>
                    <Button type="submit" disabled={submitting}>
                        <Save className="mr-1 size-4" />
                        {submitting ? 'Saving…' : 'Save'}
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Configuration</CardTitle>
                        <CardDescription>
                            Saved values override the per-install defaults declared in <code>config/cms.php</code>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        {global.fields.map((field) => {
                            const value = payload[field.key];

                            return (
                                <div key={field.key} className="space-y-1">
                                    <Label htmlFor={field.key}>{field.label}</Label>
                                    {renderField(field, value, (v) => set(field.key, v))}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

function renderField(field: Field, value: unknown, onChange: (next: unknown) => void) {
    switch (field.type) {
        case 'textarea':
            return (
                <Textarea
                    id={field.key}
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    rows={3}
                />
            );
        case 'url':
        case 'email':
            return (
                <Input
                    id={field.key}
                    type={field.type}
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
        case 'color':
            return (
                <div className="flex items-center gap-2">
                    <Input
                        type="color"
                        id={field.key}
                        value={(value as string) || '#000000'}
                        onChange={(e) => onChange(e.target.value)}
                        className="h-10 w-16 p-1"
                    />
                    <Input
                        value={(value as string) ?? ''}
                        onChange={(e) => onChange(e.target.value)}
                        className="flex-1 font-mono"
                        placeholder="#hexcode"
                    />
                </div>
            );
        case 'image':
            return (
                <>
                    <Input
                        id={field.key}
                        value={(value as string) ?? ''}
                        onChange={(e) => onChange(e.target.value)}
                        placeholder="/storage/path or https://…"
                    />
                    <p className="text-xs text-muted-foreground">Media picker arrives in M4.</p>
                </>
            );
        case 'select':
            return (
                <Select value={(value as string) ?? ''} onValueChange={onChange}>
                    <SelectTrigger id={field.key} className="w-full">
                        <SelectValue placeholder="Select…" />
                    </SelectTrigger>
                    <SelectContent>
                        {(field.options ?? []).map((opt) => (
                            <SelectItem key={opt} value={opt}>
                                {opt}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'switch':
            return (
                <Switch checked={!!value} onCheckedChange={(checked) => onChange(checked)} />
            );
        case 'number':
            return (
                <Input
                    id={field.key}
                    type="number"
                    value={(value as number | undefined) ?? ''}
                    onChange={(e) => onChange(e.target.value === '' ? null : Number(e.target.value))}
                />
            );
        case 'menu':
            return (
                <MenuEditor
                    items={(value as MenuItem[]) ?? []}
                    onChange={(next) => onChange(next)}
                />
            );
        case 'columns':
            return (
                <FooterColumnsEditor
                    columns={(value as FooterColumn[]) ?? []}
                    onChange={(next) => onChange(next)}
                />
            );
        default:
            return (
                <Input
                    id={field.key}
                    value={(value as string) ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
    }
}
