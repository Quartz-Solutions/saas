import { Form, Head } from '@inertiajs/react';
import DataExportController from '@/actions/App/Http/Controllers/Settings/DataExportController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import { index as privacyIndex } from '@/routes/privacy';

type ExportRow = {
    id: number;
    status: string;
    format: string;
    file_size_bytes: number | null;
    processed_at: string | null;
    expires_at: string | null;
    created_at: string | null;
    download_url: string | null;
};

type Props = {
    exports: ExportRow[];
};

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'ready':
            return 'default';
        case 'processing':
        case 'requested':
            return 'secondary';
        case 'failed':
        case 'expired':
            return 'destructive';
        default:
            return 'outline';
    }
}

function formatBytes(bytes: number | null): string {
    if (bytes === null || bytes === 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export default function Privacy({ exports }: Props) {
    return (
        <>
            <Head title="Privacy" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Data export"
                    description="Download a ZIP archive of all the data we hold about you. Exports take a few minutes; we'll email you when yours is ready."
                />

                <Form
                    {...DataExportController.store.form()}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="request-data-export-button"
                        >
                            {processing && <Spinner />}
                            Request data export
                        </Button>
                    )}
                </Form>

                {exports.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Recent exports</p>
                        <ul
                            className="divide-y divide-border rounded-md border"
                            data-test="exports-list"
                        >
                            {exports.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex items-center gap-4 p-4"
                                >
                                    <div className="flex-1 space-y-1">
                                        <div className="flex items-center gap-2 text-sm font-medium">
                                            Export #{row.id}
                                            <Badge
                                                variant={statusVariant(row.status)}
                                            >
                                                {row.status}
                                            </Badge>
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            Requested{' '}
                                            {row.created_at
                                                ? formatDateTime(row.created_at)
                                                : '—'}
                                            {' · '}
                                            {formatBytes(row.file_size_bytes)}
                                        </div>
                                    </div>
                                    {row.download_url && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            data-test={`export-download-${row.id}`}
                                        >
                                            <a
                                                href={row.download_url}
                                                rel="noopener noreferrer"
                                            >
                                                Download
                                            </a>
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            <DeleteUser />
        </>
    );
}

Privacy.layout = {
    breadcrumbs: [
        {
            title: 'Privacy',
            href: privacyIndex(),
        },
    ],
};
