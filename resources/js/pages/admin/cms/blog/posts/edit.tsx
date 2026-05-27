import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { store as postsStore, update as postsUpdate } from '@/actions/App/Http/Controllers/Admin/Cms/BlogPostsController';
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
import { index as postsIndex } from '@/routes/admin/cms/blog/posts';

type Post = {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    cover_image_url: string | null;
    status: 'draft' | 'published' | 'archived';
    meta_title: string | null;
    meta_description: string | null;
    no_index: boolean;
    body_blocks: Block[];
    category_ids?: number[];
    tag_ids?: number[];
};

type Term = { id: number; name: string; slug: string };

type Props = {
    post: Post | null;
    blockCatalog: BlockCatalogEntry[];
    categories: Term[];
    tags: Term[];
};

export default function BlogPostEdit({ post, blockCatalog, categories, tags }: Props) {
    const isCreate = post === null;
    const [title, setTitle] = useState(post?.title ?? '');
    const [slug, setSlug] = useState(post?.slug ?? '');
    const [excerpt, setExcerpt] = useState(post?.excerpt ?? '');
    const [coverUrl, setCoverUrl] = useState(post?.cover_image_url ?? '');
    const [status, setStatus] = useState<Post['status']>(post?.status ?? 'draft');
    const [metaTitle, setMetaTitle] = useState(post?.meta_title ?? '');
    const [metaDescription, setMetaDescription] = useState(post?.meta_description ?? '');
    const [noIndex, setNoIndex] = useState(post?.no_index ?? false);
    const [blocks, setBlocks] = useState<Block[]>(post?.body_blocks ?? []);
    const [catIds, setCatIds] = useState<number[]>(post?.category_ids ?? []);
    const [tagIds, setTagIds] = useState<number[]>(post?.tag_ids ?? []);
    const [submitting, setSubmitting] = useState(false);

    function toggle(idArr: number[], id: number): number[] {
        return idArr.includes(id) ? idArr.filter((x) => x !== id) : [...idArr, id];
    }

    function onSubmit(e: FormEvent) {
        e.preventDefault();
        setSubmitting(true);
        const payload = {
            title,
            slug: slug || undefined,
            excerpt: excerpt || null,
            cover_image_url: coverUrl || null,
            status,
            meta_title: metaTitle || null,
            meta_description: metaDescription || null,
            no_index: noIndex,
            body_blocks: blocks,
            category_ids: catIds,
            tag_ids: tagIds,
        } as unknown as Record<string, never>;

        const opts = { preserveScroll: true, onFinish: () => setSubmitting(false) };

        if (isCreate) {
            router.post(postsStore().url, payload, opts);
        } else {
            router.patch(postsUpdate({ post: post!.id }).url, payload, opts);
        }
    }

    return (
        <>
            <Head title={isCreate ? 'New post — Blog' : `Edit: ${title}`} />

            <form onSubmit={onSubmit} className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={postsIndex().url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title={isCreate ? 'New post' : `Edit: ${title || post?.title}`}
                            description={isCreate ? 'Compose a new blog post.' : `/blog/${post?.slug}`}
                        />
                    </div>
                    <Button type="submit" disabled={submitting}>
                        <Save className="mr-1 size-4" />
                        {submitting ? 'Saving…' : 'Save'}
                    </Button>
                </div>

                <Tabs defaultValue="content">
                    <TabsList>
                        <TabsTrigger value="content">Content</TabsTrigger>
                        <TabsTrigger value="meta">Meta</TabsTrigger>
                        <TabsTrigger value="taxonomy">Categories & Tags</TabsTrigger>
                        <TabsTrigger value="seo">SEO</TabsTrigger>
                    </TabsList>

                    <TabsContent value="content" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Body</CardTitle>
                                <CardDescription>Block editor — same as CMS pages.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BlockEditor blocks={blocks} catalog={blockCatalog} onChange={setBlocks} />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="meta" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Meta</CardTitle>
                                <CardDescription>Title, slug, excerpt, cover image, status.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-1 md:col-span-2">
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
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={status} onValueChange={(v) => setStatus(v as Post['status'])}>
                                        <SelectTrigger id="status" className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="published">Published</SelectItem>
                                            <SelectItem value="archived">Archived</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="excerpt">Excerpt</Label>
                                    <Textarea
                                        id="excerpt"
                                        value={excerpt}
                                        onChange={(e) => setExcerpt(e.target.value)}
                                        rows={3}
                                    />
                                </div>
                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="cover_image_url">Cover image URL</Label>
                                    <Input
                                        id="cover_image_url"
                                        value={coverUrl}
                                        onChange={(e) => setCoverUrl(e.target.value)}
                                        placeholder="/storage/...png"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="taxonomy" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Categories & tags</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-6 md:grid-cols-2">
                                <div>
                                    <h3 className="mb-2 text-sm font-semibold">Categories</h3>
                                    {categories.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">None — seed categories via admin or seeders.</p>
                                    ) : (
                                        <div className="space-y-1">
                                            {categories.map((c) => (
                                                <label key={c.id} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={catIds.includes(c.id)}
                                                        onCheckedChange={() => setCatIds(toggle(catIds, c.id))}
                                                    />
                                                    {c.name}
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div>
                                    <h3 className="mb-2 text-sm font-semibold">Tags</h3>
                                    {tags.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">None yet.</p>
                                    ) : (
                                        <div className="space-y-1">
                                            {tags.map((t) => (
                                                <label key={t.id} className="flex items-center gap-2 text-sm">
                                                    <Checkbox
                                                        checked={tagIds.includes(t.id)}
                                                        onCheckedChange={() => setTagIds(toggle(tagIds, t.id))}
                                                    />
                                                    {t.name}
                                                </label>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="seo" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>SEO</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-1">
                                    <Label htmlFor="meta_title">Meta title</Label>
                                    <Input id="meta_title" value={metaTitle} onChange={(e) => setMetaTitle(e.target.value)} />
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
                                    <Checkbox id="no_index" checked={noIndex} onCheckedChange={(c) => setNoIndex(c === true)} />
                                    <Label htmlFor="no_index">noindex</Label>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </form>
        </>
    );
}
