import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { stopImpersonating } from '@/routes/admin';

type ImpersonationProp = {
    impersonator: { id: number; name: string; email: string };
} | null;

export default function ImpersonationBanner() {
    const { impersonation } = usePage<{ impersonation: ImpersonationProp }>().props;

    if (!impersonation) {
return null;
}

    return (
        <div
            data-test="impersonation-banner"
            className="flex flex-col gap-2 border-b border-amber-500/40 bg-amber-50 px-4 py-2 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:bg-amber-950/40 dark:text-amber-100"
        >
            <span>
                You are impersonating —{' '}
                <span className="font-medium">
                    originally signed in as {impersonation.impersonator.email}
                </span>
                .
            </span>
            <Button
                size="sm"
                variant="outline"
                onClick={() => router.post(stopImpersonating().url)}
                data-test="stop-impersonating"
            >
                <LogOut className="size-4" />
                Stop impersonating
            </Button>
        </div>
    );
}
