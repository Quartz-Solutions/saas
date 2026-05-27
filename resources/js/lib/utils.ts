import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(dateString: string | null | undefined, fallback = '-'): string {
    if (!dateString) {
return fallback;
}

    return new Date(dateString).toISOString().slice(0, 10);
}

export function formatDateTime(dateString: string | null | undefined, fallback = '-'): string {
    if (!dateString) {
return fallback;
}

    return new Date(dateString).toISOString().slice(0, 19).replace('T', ' ');
}
