import { Head, Link, router } from '@inertiajs/react';
import { Inbox, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    create as formsCreate,
    destroy as formsDestroy,
    edit as formsEdit,
    submissions as formsSubmissions,
} from '@/routes/admin/cms/forms';

type Row = {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    submissions_count: number;
};

type Props = { forms: Row[] };

export default function FormsIndex({ forms }: Props) {
    function destroy(row: Row) {
        if (!confirm(`Delete form "${row.name}"?`)) {
return;
}

        router.delete(formsDestroy({ form: row.id }).url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Forms — CMS" />

            <div className="flex h-full flex-1 flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Heading title="Forms" description="Contact, newsletter, lead-capture — any embedded form." />
                    <Button asChild>
                        <Link href={formsCreate().url}>
                            <Plus className="size-4" /> New form
                        </Link>
                    </Button>
                </div>

                {forms.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border/60 bg-muted/30 px-4 py-12 text-center text-sm text-muted-foreground">
                        No forms yet.
                    </div>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        {forms.map((row) => (
                            <Card key={row.id} className="border-border/60">
                                <CardContent className="space-y-2 pt-6">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <Link href={formsEdit({ form: row.id }).url} className="font-medium hover:underline">
                                                {row.name}
                                            </Link>
                                            <p className="font-mono text-xs text-muted-foreground">{row.slug}</p>
                                        </div>
                                        <Badge variant={row.is_active ? 'default' : 'outline'}>
                                            {row.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between gap-2 pt-2">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={formsSubmissions({ form: row.id }).url}>
                                                <Inbox className="mr-1 size-4" />
                                                {row.submissions_count} submission{row.submissions_count === 1 ? '' : 's'}
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => destroy(row)} aria-label="Delete">
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
