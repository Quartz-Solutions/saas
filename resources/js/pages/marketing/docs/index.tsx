import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
import DocsSidebar from '@/components/marketing/docs-sidebar';
import SeoMeta from '@/components/marketing/seo-meta';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import docsRoutes from '@/routes/marketing/docs';

type DocPage = {
    slug: string;
    title: string;
    meta_description: string | null;
};

type Props = {
    pages: DocPage[];
};

export default function DocsIndex({ pages }: Props) {
    return (
        <>
            <SeoMeta
                pageTitle="Documentation"
                title="Documentation — guides and references"
                description="Documentation, guides and references for the boilerplate."
            />

            <div
                className="mx-auto w-full max-w-7xl px-4 py-12 md:px-6 md:py-16"
                data-test="docs-index-page"
            >
                <div className="grid gap-8 lg:grid-cols-[15rem_1fr]">
                    <aside className="hidden lg:block">
                        <DocsSidebar fallbackDocs={pages} />
                    </aside>

                    <section>
                        <div className="mb-10 max-w-2xl">
                            <BookOpen className="mb-4 size-8 text-primary" />
                            <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">
                                Documentation
                            </h1>
                            <p className="mt-4 text-muted-foreground">
                                Guides, references, and how-tos for getting the most out of the boilerplate.
                            </p>
                        </div>

                        {pages.length === 0 ? (
                            <Card>
                                <CardContent className="py-12 text-center">
                                    <p className="text-muted-foreground">
                                        No documentation pages are published yet. Create CMS pages
                                        with template <code className="rounded bg-muted px-1.5 py-0.5">docs</code>{' '}
                                        to see them here.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2" data-test="docs-list">
                                {pages.map((page) => (
                                    <Card
                                        key={page.slug}
                                        className="h-full transition-colors hover:border-primary/60"
                                        data-test="doc-card"
                                    >
                                        <Link href={docsRoutes.show(page.slug).url} className="block h-full">
                                            <CardHeader>
                                                <CardTitle className="flex items-center justify-between text-lg">
                                                    <span>{page.title}</span>
                                                    <ArrowRight className="size-4 text-muted-foreground" />
                                                </CardTitle>
                                                {page.meta_description && (
                                                    <CardDescription>{page.meta_description}</CardDescription>
                                                )}
                                            </CardHeader>
                                        </Link>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}
