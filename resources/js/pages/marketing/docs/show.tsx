import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import BlockRenderer from '@/components/cms/block-renderer';
import type { Block } from '@/components/cms/types';
import DocsSidebar from '@/components/marketing/docs-sidebar';
import SeoMeta from '@/components/marketing/seo-meta';
import { Button } from '@/components/ui/button';
import docsRoutes from '@/routes/marketing/docs';

type Page = {
    slug: string;
    title: string;
    body_html: string;
    body_blocks: Block[] | null;
    meta_title: string | null;
    meta_description: string | null;
    template: string;
    no_index: boolean;
    published_at: string | null;
};

type DocRef = { slug: string; title: string; meta_description: string | null };

type Props = {
    page: Page;
    /** Optional sibling docs for the fallback sidebar when no global is configured. */
    docs?: DocRef[];
};

export default function DocsShow({ page, docs = [] }: Props) {
    const hasBlocks = Array.isArray(page.body_blocks) && page.body_blocks.length > 0;

    return (
        <>
            <SeoMeta
                pageTitle={page.meta_title ?? page.title}
                title={page.meta_title ?? page.title}
                description={page.meta_description ?? page.title}
                type="article"
                noIndex={page.no_index}
            />

            <div
                className="mx-auto w-full max-w-7xl px-4 py-8 md:px-6 md:py-12"
                data-test="docs-show-page"
            >
                <div className="mb-4 lg:hidden">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={docsRoutes.index().url} data-test="docs-back">
                            <ArrowLeft className="mr-1 size-4" /> All docs
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-8 lg:grid-cols-[15rem_1fr]">
                    <aside className="hidden lg:block">
                        <DocsSidebar fallbackDocs={docs} activeSlug={page.slug} />
                    </aside>

                    <article className="min-w-0">
                        <header className="mb-8 border-b border-border/60 pb-6">
                            <h1
                                className="text-3xl font-semibold tracking-tight md:text-4xl"
                                data-test="docs-title"
                            >
                                {page.title}
                            </h1>
                            {page.published_at && (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Updated {new Date(page.published_at).toLocaleDateString()}
                                </p>
                            )}
                        </header>

                        {hasBlocks ? (
                            <div data-test="docs-body-blocks">
                                <BlockRenderer blocks={page.body_blocks} />
                            </div>
                        ) : (
                            <div
                                className="prose prose-neutral max-w-none dark:prose-invert"
                                dangerouslySetInnerHTML={{ __html: page.body_html }}
                                data-test="docs-body"
                            />
                        )}
                    </article>
                </div>
            </div>
        </>
    );
}
