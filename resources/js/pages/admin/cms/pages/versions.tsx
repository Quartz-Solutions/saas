import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, History, RotateCcw } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/utils';
import { edit as pagesEdit } from '@/routes/admin/cms/pages';
import {
    restore as versionsRestore,
} from '@/routes/admin/cms/pages/versions';

type Version = {
    id: number;
    version_no: number;
    note: string | null;
    author: { name: string; email: string } | null;
    created_at: string | null;
    snapshot: {
        title?: string;
        slug?: string;
        status?: string;
        body_blocks?: Array<{ type: string }>;
    };
};

type Props = {
    page: { id: number; title: string; slug: string };
    versions: Version[];
};

export default function PageVersions({ page, versions }: Props) {
    function restore(v: Version) {
        if (!confirm(`Restore page to v${v.version_no}? The current state will be snapshotted first.`)) {
return;
}

        router.post(versionsRestore({ cms_page: page.id, version: v.id }).url, {}, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Versions — ${page.title}`} />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={pagesEdit({ cms_page: page.id }).url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading
                            title={`Versions — ${page.title}`}
                            description="Each save creates a snapshot. Restoring snapshots the current state first, so it's reversible."
                        />
                    </div>
                </div>

                {versions.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        No versions yet — save the page at least once.
                    </div>
                ) : (
                    <div className="space-y-2">
                        {versions.map((v) => (
                            <Card key={v.id} className="border-border/60">
                                <CardContent className="flex items-center justify-between gap-4 pt-6">
                                    <div className="flex items-start gap-3">
                                        <History className="size-5 text-muted-foreground" />
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">v{v.version_no}</span>
                                                {v.snapshot.status && (
                                                    <span className="rounded bg-muted px-1.5 py-0.5 text-xs capitalize">
                                                        {v.snapshot.status}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-sm">{v.snapshot.title ?? '(untitled)'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {(v.snapshot.body_blocks ?? []).length} blocks · {v.author?.name ?? '—'} ·{' '}
                                                {v.created_at ? formatDateTime(v.created_at) : '—'}
                                            </p>
                                            {v.note && <p className="mt-1 text-xs italic text-muted-foreground">{v.note}</p>}
                                        </div>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => restore(v)}>
                                        <RotateCcw className="mr-1 size-4" />
                                        Restore
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
