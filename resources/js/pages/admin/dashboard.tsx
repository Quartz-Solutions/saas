import { Head, Link } from '@inertiajs/react';
import { Building2, ClipboardList, Flag, Webhook } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as featureFlagsIndex } from '@/routes/admin/feature-flags';
import { index as tenantsIndex } from '@/routes/admin/tenants';
import { index as webhooksIndex } from '@/routes/admin/webhooks';

const cards = [
    {
        title: 'Tenants',
        description: 'Search, inspect, impersonate.',
        href: tenantsIndex(),
        icon: Building2,
    },
    {
        title: 'Webhook events',
        description: 'Inspect raw payloads and replay processing.',
        href: webhooksIndex(),
        icon: Webhook,
    },
    {
        title: 'Audit log',
        description: 'Who did what when.',
        href: auditIndex(),
        icon: ClipboardList,
    },
    {
        title: 'Feature flags',
        description: 'Global toggles + per-tenant overrides.',
        href: featureFlagsIndex(),
        icon: Flag,
    },
];

export default function AdminDashboard() {
    return (
        <>
            <Head title="Admin" />
            <Heading title="Overview" description="Pick a tool to begin." />

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {cards.map((card) => (
                    <Link key={card.title} href={card.href} className="block">
                        <Card className="transition-colors hover:bg-muted/40">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <card.icon className="size-4" />
                                    {card.title}
                                </CardTitle>
                                <CardDescription>{card.description}</CardDescription>
                            </CardHeader>
                            <CardContent />
                        </Card>
                    </Link>
                ))}
            </div>
        </>
    );
}
