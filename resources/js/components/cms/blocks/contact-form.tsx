import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BlockComponentProps } from '../types';

type Attrs = {
    form_slug: string;
};

type SubmitResponse = { ok: true; message?: string } | { ok: false; message?: string };

/**
 * Renders a contact form by slug. Submits to /marketing/forms/{slug}. The
 * field schema for the form is fetched once on mount via Inertia's
 * remember + page props; for the public version we render a sensible
 * default name/email/message form when the slug-specific schema isn't
 * available in the share.
 */
export default function ContactFormBlock({ block }: BlockComponentProps<Attrs>) {
    const slug = block.attrs.form_slug;
    const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);
    const [data, setData] = useState<Record<string, string>>({ name: '', email: '', message: '' });

    async function submit(e: FormEvent) {
        e.preventDefault();

        if (!slug) {
return;
}

        setStatus('submitting');
        setError(null);

        try {
            const tokenEl = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
            const res = await fetch(`/marketing/forms/${encodeURIComponent(slug)}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': tokenEl?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ...data, _honey: '' }),
            });
            const body: SubmitResponse = await res.json().catch(() => ({ ok: false }));

            if (!res.ok || !body.ok) {
                setStatus('error');
                setError(body.message || `Submit failed (${res.status})`);

                return;
            }

            setStatus('success');
            setSuccess(body.message || 'Thanks! We received your message.');
            setData({ name: '', email: '', message: '' });
        } catch (e: unknown) {
            setStatus('error');
            setError(e instanceof Error ? e.message : 'Submit failed');
        }
    }

    if (!slug) {
return null;
}

    return (
        <section className="py-16" data-test="block-contact-form">
            <div className="mx-auto w-full max-w-xl px-4 md:px-6">
                {status === 'success' ? (
                    <div className="rounded-md border border-emerald-500/40 bg-emerald-500/10 p-6 text-center text-sm text-emerald-700 dark:text-emerald-300">
                        {success}
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-4">
                        {/* Honeypot — hidden from real users. */}
                        <input type="text" name="_honey" tabIndex={-1} autoComplete="off" className="absolute left-[-9999px]" aria-hidden="true" />

                        <div className="space-y-1">
                            <Label htmlFor="cf-name">Name</Label>
                            <Input
                                id="cf-name"
                                value={data.name}
                                onChange={(e) => setData({ ...data, name: e.target.value })}
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="cf-email">Email</Label>
                            <Input
                                id="cf-email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData({ ...data, email: e.target.value })}
                                required
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="cf-message">Message</Label>
                            <Textarea
                                id="cf-message"
                                value={data.message}
                                onChange={(e) => setData({ ...data, message: e.target.value })}
                                rows={5}
                                required
                            />
                        </div>
                        {error && <p className="text-sm text-destructive">{error}</p>}
                        <Button type="submit" disabled={status === 'submitting'}>
                            {status === 'submitting' ? 'Sending…' : 'Send message'}
                        </Button>
                    </form>
                )}
            </div>
        </section>
    );
}
