import { Link } from '@inertiajs/react';
import { ArrowRight, BookOpen } from 'lucide-react';
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

            <section
                className="mx-auto w-full max-w-5xl px-4 py-16 md:px-6 md:py-24"
                data-test="docs-index-page"
            >
                <div className="mx-auto max-w-2xl text-center">
                    <BookOpen className="mx-auto mb-4 size-8 text-primary" />
                    <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">
                        Documentation
                    </h1>
                    <p className="mt-4 text-muted-foreground">
                        Guides, references and how-tos for getting the most out of the
                        boilerplate.
                    </p>
                </div>

                {pages.length === 0 ? (
                    <Card className="mt-12">
                        <CardContent className="py-12 text-center">
                            <p className="text-muted-foreground">
                                No documentation pages are published yet. Create CMS pages
                                with template <code className="rounded bg-muted px-1.5 py-0.5">docs</code>{' '}
                                to see them here.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div
                        className="mt-12 grid gap-4 md:grid-cols-2"
                        data-test="docs-list"
                    >
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
                                            <CardDescription>
                                                {page.meta_description}
                                            </CardDescription>
                                        )}
                                    </CardHeader>
                                </Link>
                            </Card>
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
