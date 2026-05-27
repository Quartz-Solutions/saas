import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { BlockComponentProps } from '../types';

type Attrs = {
    title?: string;
    body?: string;
    success_message?: string;
};

/**
 * Newsletter signup block. In M10 this will POST to the active
 * NewsletterProvider via `/marketing/newsletter/subscribe`. For now the
 * form is wired to the future endpoint and shows the success copy locally.
 */
export default function NewsletterBlock({ block }: BlockComponentProps<Attrs>) {
    const { title, body, success_message = "Thanks! We'll be in touch." } = block.attrs;
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);

    async function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setStatus('submitting');
        setError(null);

        try {
            const tokenEl = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
            const res = await fetch('/marketing/newsletter/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': tokenEl?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ email, source: 'newsletter_block' }),
            });

            if (!res.ok) {
                const data: { message?: string } = await res.json().catch(() => ({}));

                throw new Error(data.message || 'Subscription failed');
            }

            setStatus('success');
            setEmail('');
        } catch (e: unknown) {
            setStatus('error');
            setError(e instanceof Error ? e.message : 'Something went wrong');
        }
    }

    return (
        <section className="py-16" data-test="block-newsletter">
            <div className="mx-auto w-full max-w-xl px-4 text-center md:px-6">
                {title && <h2 className="text-2xl font-semibold md:text-3xl">{title}</h2>}
                {body && <p className="mt-3 text-muted-foreground">{body}</p>}

                {status === 'success' ? (
                    <p className="mt-6 text-sm text-primary">{success_message}</p>
                ) : (
                    <form onSubmit={handleSubmit} className="mt-6 flex flex-col gap-2 sm:flex-row">
                        <Input
                            type="email"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="you@example.com"
                            className="flex-1"
                            aria-label="Email address"
                        />
                        <Button type="submit" disabled={status === 'submitting'}>
                            {status === 'submitting' ? 'Subscribing…' : 'Subscribe'}
                        </Button>
                    </form>
                )}
                {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
            </div>
        </section>
    );
}
