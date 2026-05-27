import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/utils';
import {
    create as postsCreate,
    destroy as postsDestroy,
    edit as postsEdit,
} from '@/routes/admin/cms/blog/posts';

type Row = {
    id: number;
    slug: string;
    title: string;
    status: 'draft' | 'published' | 'archived';
    reading_minutes: number | null;
    published_at: string | null;
    updated_at: string | null;
    author: { id: number; name: string } | null;
    categories: Array<{ id: number; name: string }>;
};

type Props = {
    posts: { data: Row[]; meta: { current_page: number; last_page: number; per_page: number; total: number } };
};

const STATUS_VARIANT: Record<Row['status'], 'default' | 'secondary' | 'outline'> = {
    published: 'default',
    draft: 'secondary',
    archived: 'outline',
};

export default function BlogPostsIndex({ posts }: Props) {
    function destroy(row: Row) {
        if (!confirm(`Archive "${row.title}"?`)) {
return;
}

        router.delete(postsDestroy({ post: row.id }).url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Blog posts — CMS" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Blog posts"
                        description="Articles published on the public /blog. Block-edited with the same editor as pages."
                    />
                    <Button asChild>
                        <Link href={postsCreate().url}>
                            <Plus className="size-4" />
                            New post
                        </Link>
                    </Button>
                </div>

                {posts.data.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        No posts yet.
                    </div>
                ) : (
                    <div className="rounded-md border border-border/60">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">Title</th>
                                    <th className="px-3 py-2 text-left font-medium">Status</th>
                                    <th className="px-3 py-2 text-left font-medium">Author</th>
                                    <th className="px-3 py-2 text-left font-medium">Reading</th>
                                    <th className="px-3 py-2 text-left font-medium">Published</th>
                                    <th className="w-px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {posts.data.map((row) => (
                                    <tr key={row.id} className="border-t border-border/40">
                                        <td className="px-3 py-2">
                                            <Link href={postsEdit({ post: row.id }).url} className="font-medium hover:underline">
                                                {row.title}
                                            </Link>
                                            <div className="font-mono text-xs text-muted-foreground">/blog/{row.slug}</div>
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge variant={STATUS_VARIANT[row.status]} className="capitalize">
                                                {row.status}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">{row.author?.name ?? '—'}</td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {row.reading_minutes ? `${row.reading_minutes} min` : '—'}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                            {row.published_at ? formatDateTime(row.published_at) : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="size-7">
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={postsEdit({ post: row.id }).url}>Edit</Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem variant="destructive" onSelect={() => destroy(row)}>
                                                        <Trash2 className="size-4" />
                                                        Archive
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}
