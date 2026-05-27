import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/utils';
import { index as formsIndex, submissionsCsv } from '@/routes/admin/cms/forms';

type Field = { key: string; label: string };

type Submission = {
    id: number;
    payload: Record<string, unknown>;
    ip: string | null;
    created_at: string | null;
};

type Props = {
    form: { id: number; slug: string; name: string; fields: Field[] };
    submissions: { data: Submission[]; meta: { current_page: number; last_page: number; total: number } };
};

export default function FormSubmissionsPage({ form, submissions }: Props) {
    return (
        <>
            <Head title={`${form.name} — Submissions`} />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button asChild variant="ghost" size="icon" type="button">
                            <Link href={formsIndex().url}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <Heading title={`${form.name} — Submissions`} description={`Inbox for /marketing/forms/${form.slug}`} />
                    </div>
                    <Button asChild variant="outline">
                        <a href={submissionsCsv({ form: form.id }).url}>
                            <Download className="mr-1 size-4" />
                            Export CSV
                        </a>
                    </Button>
                </div>

                {submissions.data.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        No submissions yet.
                    </div>
                ) : (
                    <div className="space-y-3">
                        {submissions.data.map((s) => (
                            <Card key={s.id}>
                                <CardContent className="space-y-2 pt-6">
                                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span>#{s.id}</span>
                                        <span>{s.created_at ? formatDateTime(s.created_at) : '—'} · {s.ip ?? 'unknown ip'}</span>
                                    </div>
                                    <dl className="space-y-1">
                                        {Object.entries(s.payload).map(([key, value]) => (
                                            <div key={key} className="grid grid-cols-4 gap-2 text-sm">
                                                <dt className="col-span-1 font-medium text-muted-foreground">{key}</dt>
                                                <dd className="col-span-3 whitespace-pre-wrap">{String(value ?? '')}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
