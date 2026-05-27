import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import SeoMeta from '@/components/marketing/seo-meta';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as blogIndex, show as blogShow } from '@/routes/marketing/blog';

type Post = {
    slug: string;
    title: string;
    excerpt: string | null;
    cover_image_url: string | null;
    published_at: string | null;
    reading_minutes: number | null;
};

type Props = {
    archive: { kind: 'category' | 'tag'; slug: string; name: string };
    posts: Post[];
};

export default function BlogArchive({ archive, posts }: Props) {
    return (
        <>
            <SeoMeta
                title={`${archive.name} — Blog`}
                description={`Posts ${archive.kind === 'category' ? 'in' : 'tagged'} ${archive.name}.`}
            />

            <article className="mx-auto w-full max-w-4xl px-4 py-12 md:px-6 md:py-16">
                <div className="mb-6">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={blogIndex().url}>
                            <ArrowLeft className="mr-1 size-4" /> All posts
                        </Link>
                    </Button>
                </div>

                <header className="mb-10">
                    <p className="text-sm uppercase tracking-wide text-muted-foreground">
                        {archive.kind === 'category' ? 'Category' : 'Tag'}
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight md:text-4xl">{archive.name}</h1>
                </header>

                {posts.length === 0 ? (
                    <p className="text-muted-foreground">No posts here yet.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {posts.map((post) => (
                            <Link key={post.slug} href={blogShow({ slug: post.slug }).url}>
                                <Card className="h-full transition-shadow hover:shadow-md">
                                    <CardContent className="space-y-2 pt-4">
                                        <h2 className="text-lg font-semibold">{post.title}</h2>
                                        {post.excerpt && (
                                            <p className="line-clamp-3 text-sm text-muted-foreground">{post.excerpt}</p>
                                        )}
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </article>
        </>
    );
}
