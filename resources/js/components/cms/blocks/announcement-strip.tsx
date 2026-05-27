import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { BlockComponentProps } from '../types';

type Attrs = {
    message: string;
    link_label?: string;
    link_url?: string;
    variant?: 'info' | 'success' | 'warning';
};

const VARIANT_CLASS: Record<NonNullable<Attrs['variant']>, string> = {
    info: 'bg-primary/10 text-primary',
    success: 'bg-emerald-500/10 text-emerald-600',
    warning: 'bg-amber-500/10 text-amber-700',
};

export default function AnnouncementStripBlock({ block }: BlockComponentProps<Attrs>) {
    const { message, link_label, link_url, variant = 'info' } = block.attrs;

    if (!message) {
return null;
}

    return (
        <div className={cn('w-full px-4 py-2 text-center text-sm', VARIANT_CLASS[variant])} data-test="block-announcement-strip">
            <span>{message}</span>
            {link_label && link_url && (
                <Link href={link_url} className="ml-2 underline">
                    {link_label}
                </Link>
            )}
        </div>
    );
}
