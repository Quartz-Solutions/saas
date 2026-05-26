import { Head, Link } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDashed,
    ExternalLink,
    Settings2,
    XCircle,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit as gatewayEdit } from '@/routes/admin/gateways';

type Gateway = {
    id: string;
    name: string;
    description: string | null;
    regions: string[];
    capabilities: string[];
    driver_status: 'shipped' | 'planned';
    documentation_url: string | null;
    enabled: boolean;
    configured: boolean;
    has_fields: boolean;
    active_subscriptions: number;
};

type Props = {
    gateways: Gateway[];
};

const CAPABILITY_LABEL: Record<string, string> = {
    subscriptions: 'Subscriptions',
    one_time: 'One-time',
    refunds: 'Refunds',
    customer_portal: 'Portal',
};

export default function GatewaysIndex({ gateways }: Props) {
    return (
        <>
            <Head title="Payment gateways — Admin" />

            <Heading
                title="Payment gateways"
                description="Configure credentials per gateway. Stripe is the only shipped driver — the rest land in Phase 3.2–3.4 but you can save credentials early."
            />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {gateways.map((g) => (
                    <GatewayCard key={g.id} gateway={g} />
                ))}
            </div>
        </>
    );
}

function GatewayCard({ gateway }: { gateway: Gateway }) {
    const shipped = gateway.driver_status === 'shipped';

    return (
        <Card className="flex flex-col">
            <CardHeader>
                <div className="flex items-start justify-between gap-2">
                    <CardTitle className="text-base">{gateway.name}</CardTitle>
                    <StatusBadge gateway={gateway} />
                </div>
                <CardDescription className="line-clamp-3">
                    {gateway.description ?? '—'}
                </CardDescription>
            </CardHeader>

            <CardContent className="flex-1 space-y-3">
                <div className="flex flex-wrap gap-1">
                    {gateway.regions.map((region) => (
                        <Badge key={region} variant="outline" className="text-xs">
                            {region}
                        </Badge>
                    ))}
                </div>

                <div className="flex flex-wrap gap-1">
                    {gateway.capabilities.map((cap) => (
                        <Badge key={cap} variant="secondary" className="text-xs">
                            {CAPABILITY_LABEL[cap] ?? cap}
                        </Badge>
                    ))}
                </div>

                {gateway.active_subscriptions > 0 ? (
                    <p className="text-xs text-muted-foreground">
                        {gateway.active_subscriptions} active subscription
                        {gateway.active_subscriptions === 1 ? '' : 's'} on this gateway.
                    </p>
                ) : null}

                {!shipped ? (
                    <p className="text-xs text-amber-700 dark:text-amber-300">
                        Driver coming in a later phase. You can save credentials in advance —
                        enabling is locked until the driver ships.
                    </p>
                ) : null}
            </CardContent>

            <CardFooter className="flex flex-wrap gap-2">
                <Button asChild variant={shipped ? 'default' : 'outline'} size="sm">
                    <Link href={gatewayEdit({ gateway: gateway.id })}>
                        <Settings2 className="size-4" />
                        {gateway.has_fields ? 'Configure' : 'View'}
                    </Link>
                </Button>
                {gateway.documentation_url ? (
                    <Button asChild variant="ghost" size="sm">
                        <a
                            href={gateway.documentation_url}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink className="size-3.5" />
                            Docs
                        </a>
                    </Button>
                ) : null}
            </CardFooter>
        </Card>
    );
}

function StatusBadge({ gateway }: { gateway: Gateway }) {
    if (gateway.driver_status !== 'shipped') {
        return (
            <Badge variant="outline" className="gap-1 text-xs">
                <CircleDashed className="size-3" />
                Planned
            </Badge>
        );
    }

    if (gateway.enabled && gateway.configured) {
        return (
            <Badge className="gap-1 bg-emerald-600 text-xs hover:bg-emerald-600">
                <CheckCircle2 className="size-3" />
                Live
            </Badge>
        );
    }

    if (gateway.configured) {
        return (
            <Badge variant="secondary" className="gap-1 text-xs">
                <CircleDashed className="size-3" />
                Configured (off)
            </Badge>
        );
    }

    return (
        <Badge variant="outline" className="gap-1 text-xs">
            <XCircle className="size-3" />
            Not configured
        </Badge>
    );
}
