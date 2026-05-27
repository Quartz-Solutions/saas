import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { stopImpersonating } from '@/routes/admin';

type ImpersonationProp = {
    impersonator: { id: number; name: string; email: string };
} | null;

export default function AdminLayout({ children }: PropsWithChildren) {
    const { impersonation } = usePage<{ impersonation: ImpersonationProp }>().props;

    return (
        <div className="px-4 py-6">
            {impersonation && (
                <div
                    data-test="impersonation-banner"
                    className="mb-6 flex flex-col gap-2 rounded-md border border-amber-500/40 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:bg-amber-950/40 dark:text-amber-100"
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
                    >
                        <LogOut className="size-4" />
                        Stop impersonating
                    </Button>
                </div>
            )}

            <Heading
                title="Admin"
                description="Internal staff tools — bypasses tenant scoping."
            />

            <div className="mt-6">{children}</div>
        </div>
    );
}
