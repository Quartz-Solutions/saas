import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, RefreshCw, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { store as pagesStore, update as pagesUpdate } from '@/actions/App/Http/Controllers/Admin/Cms/PagesController';
import BlockEditor from '@/components/cms/admin/block-editor';
import type { BlockCatalogEntry } from '@/components/cms/admin/block-picker';
import type { Block } from '@/components/cms/types';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { index as pagesIndex, previewLink as pagesPreviewLink } from '@/routes/admin/cms/pages';

type PageRecord = {
    id: number;
    slug: string;
    title: string;
    locale: string;
    parent_id: number | null;
    path: string | null;
    route_name: string | null;
    template: string;
    status: 'draft' | 'published' | 'archived';
    meta_title: string | null;
    meta_description: string | null;
    no_index: boolean;
    published_at: string | null;
    publish_at: string | null;
    unpublish_at: string | null;
    body_blocks: Block[];
    body_html: string;
};

type Props = {
    page: PageRecord | null;
    blockCatalog: BlockCatalogEntry[];
    reservedRoutes: Record<string, string>;
    parentOptions: Array<{ id: number; title: string; path: string | null }>;
    locales: Array<{ code: string; label: string }>;
};

const TEMPLATES = [
    { value: 'default', label: 'Default' },
    { value: 'landing', label: 'Landing' },
    { value: 'docs', label: 'Docs' },
    { value: 'legal', label: 'Legal' },
];

const STATUSES = [
    { value: 'draft', label: 'Draft' },
    { value: 'published', label: 'Published' },
    { value: 'archived', label: 'Archived' },
];

export default function CmsPagesEdit({ page, blockCatalog, reservedRoutes, parentOptions, locales }: Props) {
    const isCreate = page === null;

    const [title, setTitle] = useState(page?.title ?? '');
    const [slug, setSlug] = useState(page?.slug ?? '');
    const [locale, setLocale] = useState(page?.locale ?? 'en');
    const [parentId, setParentId] = useState<string>(page?.parent_id ? String(page.parent_id) : '');
    const [template, setTemplate] = useState(page?.template ?? 'default');
    const [status, setStatus] = useState<'draft' | 'published' | 'archived'>(page?.status ?? 'draft');
    const [routeName, setRouteName] = useState(page?.route_name ?? '');
    const [metaTitle, setMetaTitle] = useState(page?.meta_title ?? '');
    const [metaDescription, setMetaDescription] = useState(page?.meta_description ?? '');
    const [noIndex, setNoIndex] = useState(page?.no_index ?? false);
    const [blocks, setBlocks] = useState<Block[]>(page?.body_blocks ?? []);
    const [submitting, setSubmitting] = useState(false);

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);

        const payload = {
            title,
            slug: slug || undefined,
            locale,
            parent_id: parentId === '' ? null : Number(parentId),
            route_name: routeName || null,
            template,
            status,
            meta_title: metaTitle || null,
            meta_description: metaDescription || null,
            no_index: noIndex,
            body_blocks: blocks,
        };

        const opts = {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
        };

        // Inertia router types are conservative about RequestPayload —
        // body_blocks is a nested object array, which is supported at runtime
        // but trips the index-signature check. Cast through unknown to satisfy.
        const data = payload as unknown as Record<string, never>;

        if (isCreate) {
            router.post(pagesStore().url, data, opts);
        } else {
            router.patch(pagesUpdate({ cms_page: page!.id }).url, data, opts);
        }
    }

    return (
        <>
            <Head title={isCreate ? 'New page — CMS' : `Edit page — ${title}`} />

            <form onSubmit={onSubmit} className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={pagesIndex().url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title={isCreate ? 'New page' : `Edit: ${title || page?.title}`}
                            description={isCreate ? 'Compose a new page from blocks.' : `/${page?.path ?? page?.slug}`}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={submitting} data-test="save-page">
                            <Save className="mr-1 size-4" />
                            {submitting ? 'Saving…' : 'Save'}
                        </Button>
                    </div>
                </div>

                <Tabs defaultValue="content" className="flex-1">
                    <TabsList>
                        <TabsTrigger value="content">Content</TabsTrigger>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                        <TabsTrigger value="seo">SEO</TabsTrigger>
                        {!isCreate && <TabsTrigger value="preview">Preview</TabsTrigger>}
                    </TabsList>

                    <TabsContent value="content" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Page content</CardTitle>
                                <CardDescription>
                                    Drag the grip handle to reorder. Click a block header to edit its attributes.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BlockEditor blocks={blocks} catalog={blockCatalog} onChange={setBlocks} />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="settings" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Settings</CardTitle>
                                <CardDescription>Identifying info, template, parent, schedule.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="title">Title</Label>
                                    <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} required />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="slug">Slug</Label>
                                    <Input
                                        id="slug"
                                        value={slug}
                                        onChange={(e) => setSlug(e.target.value)}
                                        placeholder="auto-from-title"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="locale">Locale</Label>
                                    <Select value={locale} onValueChange={setLocale}>
                                        <SelectTrigger id="locale" className="w-full">
                                            <SelectValue placeholder="Locale" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {locales.map((l) => (
                                                <SelectItem key={l.code} value={l.code}>
                                                    {l.label} ({l.code})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="template">Template</Label>
                                    <Select value={template} onValueChange={setTemplate}>
                                        <SelectTrigger id="template" className="w-full">
                                            <SelectValue placeholder="Template" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {TEMPLATES.map((t) => (
                                                <SelectItem key={t.value} value={t.value}>
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={status} onValueChange={(v) => setStatus(v as typeof status)}>
                                        <SelectTrigger id="status" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {STATUSES.map((s) => (
                                                <SelectItem key={s.value} value={s.value}>
                                                    {s.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="parent_id">Parent page</Label>
                                    <Select
                                        value={parentId === '' ? '__none__' : parentId}
                                        onValueChange={(v) => setParentId(v === '__none__' ? '' : v)}
                                    >
                                        <SelectTrigger id="parent_id" className="w-full">
                                            <SelectValue placeholder="Top-level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">Top-level (no parent)</SelectItem>
                                            {parentOptions.map((p) => (
                                                <SelectItem key={p.id} value={String(p.id)}>
                                                    {p.title} {p.path ? `(/${p.path})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="route_name">Route slot (optional)</Label>
                                    <Select
                                        value={routeName === '' ? '__none__' : routeName}
                                        onValueChange={(v) => setRouteName(v === '__none__' ? '' : v)}
                                    >
                                        <SelectTrigger id="route_name" className="w-full">
                                            <SelectValue placeholder="No reserved slot" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none__">No reserved slot</SelectItem>
                                            {Object.entries(reservedRoutes).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        Claim a reserved URL slot (M5 wires these to override the hardcoded controllers).
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {!isCreate && (
                        <TabsContent value="preview" className="space-y-4">
                            <PreviewTab pageId={page!.id} />
                        </TabsContent>
                    )}

                    <TabsContent value="seo" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>SEO</CardTitle>
                                <CardDescription>
                                    Search engine + social preview metadata. M6 adds JSON-LD and OG image picker.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <Label htmlFor="meta_title">Meta title</Label>
                                    <Input
                                        id="meta_title"
                                        value={metaTitle}
                                        onChange={(e) => setMetaTitle(e.target.value)}
                                        placeholder={`Defaults to the page title (${title || '—'})`}
                                    />
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="meta_description">Meta description</Label>
                                    <Textarea
                                        id="meta_description"
                                        value={metaDescription}
                                        onChange={(e) => setMetaDescription(e.target.value)}
                                        rows={3}
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="no_index"
                                        checked={noIndex}
                                        onCheckedChange={(checked) => setNoIndex(checked === true)}
                                    />
                                    <Label htmlFor="no_index" className="cursor-pointer">
                                        Hide from search engines (noindex)
                                    </Label>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </form>
        </>
    );
}

function PreviewTab({ pageId }: { pageId: number }) {
    const [url, setUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [viewport, setViewport] = useState<'mobile' | 'tablet' | 'desktop'>('desktop');
    const iframeRef = useRef<HTMLIFrameElement>(null);

    async function refresh() {
        setError(null);

        try {
            const res = await fetch(pagesPreviewLink({ cms_page: pageId }).url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) {
throw new Error(`Failed (${res.status})`);
}

            const data: { url: string } = await res.json();
            setUrl(data.url);

            // Hard-refresh the iframe by setting src.
            if (iframeRef.current) {
iframeRef.current.src = data.url;
}
        } catch (e: unknown) {
            setError(e instanceof Error ? e.message : 'Could not load preview.');
        }
    }

    useEffect(() => {
        void refresh();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pageId]);

    const viewportClass =
        viewport === 'mobile' ? 'max-w-[390px]' : viewport === 'tablet' ? 'max-w-[768px]' : 'max-w-full';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Live preview</CardTitle>
                <CardDescription>
                    Signed URL valid for 30 minutes. Save the page, then hit refresh to see your latest changes.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={refresh}>
                        <RefreshCw className="mr-1 size-4" />
                        Refresh
                    </Button>
                    {url && (
                        <Button asChild type="button" variant="ghost" size="sm">
                            <a href={url} target="_blank" rel="noreferrer">
                                <ExternalLink className="mr-1 size-4" />
                                Open in new tab
                            </a>
                        </Button>
                    )}
                    <div className="ml-auto flex items-center gap-1 rounded-md border border-border/60 p-0.5">
                        {(['mobile', 'tablet', 'desktop'] as const).map((vp) => (
                            <Button
                                key={vp}
                                type="button"
                                variant={viewport === vp ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setViewport(vp)}
                            >
                                {vp}
                            </Button>
                        ))}
                    </div>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
                <div className="mx-auto overflow-hidden rounded-md border border-border/60 bg-muted/20 transition-all">
                    <div className={`mx-auto ${viewportClass} transition-all`}>
                        <iframe
                            ref={iframeRef}
                            title="Page preview"
                            className="h-[800px] w-full"
                            src={url ?? 'about:blank'}
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
