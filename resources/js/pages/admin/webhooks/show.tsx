import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, RotateCw } from 'lucide-react';
import WebhookEventsController from '@/actions/App/Http/Controllers/Admin/WebhookEventsController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/utils';
import { index as webhooksIndex } from '@/routes/admin/webhooks';

type Event = {
    id: number;
    gateway: string;
    gateway_event_id: string;
    event_type: string;
    status: string;
    processing_attempts: number;
    error_message: string | null;
    signature: string | null;
    headers: Record<string, unknown> | null;
    payload: Record<string, unknown> | null;
    received_at: string | null;
    processed_at: string | null;
    tenant_id: number | null;
};

export default function AdminWebhookShow({ webhookEvent }: { webhookEvent: Event }) {
    return (
        <>
            <Head title={`Webhook #${webhookEvent.id}`} />

            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-4">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={webhooksIndex()}>
                            <ArrowLeft className="size-4" />
                            Back to webhooks
                        </Link>
                    </Button>
                    <Form
                        {...WebhookEventsController.replay.form({ webhookEvent: webhookEvent.id })}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                <RotateCw className="size-4" />
                                Replay
                            </Button>
                        )}
                    </Form>
                </div>

                <Heading
                    title={webhookEvent.event_type}
                    description={`${webhookEvent.gateway.toUpperCase()} · ${webhookEvent.gateway_event_id}`}
                />

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Metadata</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-3 text-sm">
                            <Field label="Status">
                                <Badge variant="outline">{webhookEvent.status}</Badge>
                            </Field>
                            <Field label="Attempts">
                                <span className="tabular-nums">
                                    {webhookEvent.processing_attempts}
                                </span>
                            </Field>
                            <Field label="Received">
                                {webhookEvent.received_at
                                    ? formatDateTime(webhookEvent.received_at)
                                    : '—'}
                            </Field>
                            <Field label="Processed">
                                {webhookEvent.processed_at
                                    ? formatDateTime(webhookEvent.processed_at)
                                    : '—'}
                            </Field>
                            <Field label="Tenant ID">
                                {webhookEvent.tenant_id ?? '—'}
                            </Field>
                            <Field label="Signature">
                                <span className="break-all font-mono text-xs text-muted-foreground">
                                    {webhookEvent.signature ?? '—'}
                                </span>
                            </Field>
                        </CardContent>
                    </Card>

                    {webhookEvent.error_message && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-destructive">
                                    Error
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <pre className="overflow-x-auto whitespace-pre-wrap text-xs text-destructive">
                                    {webhookEvent.error_message}
                                </pre>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Payload</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre
                            data-test="webhook-payload"
                            className="overflow-x-auto rounded-md bg-muted/40 p-4 text-xs"
                        >
                            {JSON.stringify(webhookEvent.payload, null, 2)}
                        </pre>
                    </CardContent>
                </Card>

                {webhookEvent.headers && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Headers</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre className="overflow-x-auto rounded-md bg-muted/40 p-4 text-xs">
                                {JSON.stringify(webhookEvent.headers, null, 2)}
                            </pre>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[11px] uppercase tracking-wide text-muted-foreground">
                {label}
            </span>
            <span>{children}</span>
        </div>
    );
}
