import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import BlockRenderer from '@/components/cms/block-renderer';
import type { Block } from '@/components/cms/types';
import SeoMeta from '@/components/marketing/seo-meta';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as blogIndex } from '@/routes/marketing/blog';

type Post = {
    slug: string;
    title: string;
    excerpt: string | null;
    cover_image_url: string | null;
    published_at: string | null;
    reading_minutes: number | null;
    no_index: boolean;
    meta_title: string | null;
    meta_description: string | null;
    body_blocks: Block[];
    body_html: string;
    author: { id: number; name: string } | null;
    categories: Array<{ slug: string; name: string }>;
    tags: Array<{ slug: string; name: string }>;
};

type Props = {
    post: Post;
};

export default function BlogShow({ post }: Props) {
    const hasBlocks = Array.isArray(post.body_blocks) && post.body_blocks.length > 0;

    return (
        <>
            <SeoMeta
                title={post.meta_title ?? post.title}
                description={post.meta_description ?? post.excerpt ?? post.title}
                type="article"
                noIndex={post.no_index}
                ogImage={post.cover_image_url}
                schemaOrg={{
                    '@context': 'https://schema.org',
                    '@type': 'Article',
                    headline: post.title,
                    datePublished: post.published_at,
                    author: post.author ? { '@type': 'Person', name: post.author.name } : undefined,
                    image: post.cover_image_url,
                }}
            />

            <article className="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16" data-test="blog-show">
                <div className="mb-6">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={blogIndex().url}>
                            <ArrowLeft className="mr-1 size-4" /> All posts
                        </Link>
                    </Button>
                </div>

                {post.cover_image_url && (
                    <img
                        src={post.cover_image_url}
                        alt=""
                        className="mb-8 w-full rounded-md border border-border/40"
                    />
                )}

                <header className="mb-8 border-b border-border/60 pb-6">
                    <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {post.published_at && (
                            <span>{new Date(post.published_at).toLocaleDateString()}</span>
                        )}
                        {post.reading_minutes && <span>· {post.reading_minutes} min read</span>}
                        {post.author && <span>· {post.author.name}</span>}
                    </div>
                    <h1 className="mt-3 text-3xl font-semibold tracking-tight md:text-4xl">{post.title}</h1>
                    {post.excerpt && <p className="mt-3 text-lg text-muted-foreground">{post.excerpt}</p>}
                </header>

                {hasBlocks ? (
                    <div data-test="blog-body-blocks">
                        <BlockRenderer blocks={post.body_blocks} />
                    </div>
                ) : (
                    <div
                        className="prose prose-neutral max-w-none dark:prose-invert"
                        dangerouslySetInnerHTML={{ __html: post.body_html }}
                    />
                )}

                {(post.categories.length > 0 || post.tags.length > 0) && (
                    <footer className="mt-12 border-t border-border/60 pt-6">
                        <div className="flex flex-wrap gap-2">
                            {post.categories.map((c) => (
                                <Badge key={c.slug} variant="secondary">
                                    {c.name}
                                </Badge>
                            ))}
                            {post.tags.map((t) => (
                                <Badge key={t.slug} variant="outline">
                                    #{t.name}
                                </Badge>
                            ))}
                        </div>
                    </footer>
                )}
            </article>
        </>
    );
}
