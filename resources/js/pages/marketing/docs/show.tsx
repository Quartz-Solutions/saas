import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SeoMeta from '@/components/marketing/seo-meta';
import { Button } from '@/components/ui/button';
import docsRoutes from '@/routes/marketing/docs';

type Page = {
    slug: string;
    title: string;
    body_html: string;
    meta_title: string | null;
    meta_description: string | null;
    template: string;
    no_index: boolean;
    published_at: string | null;
};

type Props = {
    page: Page;
};

export default function DocsShow({ page }: Props) {
    return (
        <>
            <SeoMeta
                pageTitle={page.meta_title ?? page.title}
                title={page.meta_title ?? page.title}
                description={page.meta_description ?? page.title}
                type="article"
                noIndex={page.no_index}
            />

            <article
                className="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16"
                data-test="docs-show-page"
            >
                <div className="mb-6">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={docsRoutes.index().url} data-test="docs-back">
                            <ArrowLeft className="mr-1 size-4" /> All docs
                        </Link>
                    </Button>
                </div>

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

                <div
                    className="prose prose-neutral max-w-none dark:prose-invert"
                    dangerouslySetInnerHTML={{ __html: page.body_html }}
                    data-test="docs-body"
                />
            </article>
        </>
    );
}
