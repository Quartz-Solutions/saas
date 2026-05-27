import { Link } from '@inertiajs/react';
import SeoMeta from '@/components/marketing/seo-meta';
import { Card, CardContent } from '@/components/ui/card';
import { show as blogShow } from '@/routes/marketing/blog';

type Post = {
    slug: string;
    title: string;
    excerpt: string | null;
    cover_image_url: string | null;
    published_at: string | null;
    reading_minutes: number | null;
    author: { id: number; name: string } | null;
    categories: Array<{ slug: string; name: string }>;
    tags: Array<{ slug: string; name: string }>;
};

type Props = {
    posts: {
        data: Post[];
        meta: { current_page: number; last_page: number; per_page: number; total: number };
    };
    categories: Array<{ slug: string; name: string }>;
    tags: Array<{ slug: string; name: string }>;
};

export default function BlogIndex({ posts }: Props) {
    return (
        <>
            <SeoMeta title="Blog" description="Latest articles and updates." />

            <article className="mx-auto w-full max-w-4xl px-4 py-12 md:px-6 md:py-16" data-test="blog-index">
                <header className="mb-10">
                    <h1 className="text-4xl font-semibold tracking-tight md:text-5xl">Blog</h1>
                    <p className="mt-2 text-muted-foreground">Articles, product updates, and engineering notes.</p>
                </header>

                {posts.data.length === 0 ? (
                    <p className="text-muted-foreground">No posts yet.</p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {posts.data.map((post) => (
                            <Link key={post.slug} href={blogShow({ slug: post.slug }).url}>
                                <Card className="h-full transition-shadow hover:shadow-md">
                                    {post.cover_image_url && (
                                        <div className="aspect-video w-full overflow-hidden">
                                            <img src={post.cover_image_url} alt="" className="size-full object-cover" />
                                        </div>
                                    )}
                                    <CardContent className="space-y-2 pt-4">
                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            {post.published_at && (
                                                <span>{new Date(post.published_at).toLocaleDateString()}</span>
                                            )}
                                            {post.reading_minutes && <span>· {post.reading_minutes} min read</span>}
                                        </div>
                                        <h2 className="text-lg font-semibold">{post.title}</h2>
                                        {post.excerpt && (
                                            <p className="line-clamp-3 text-sm text-muted-foreground">{post.excerpt}</p>
                                        )}
                                        {post.author && (
                                            <p className="text-xs text-muted-foreground">By {post.author.name}</p>
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
