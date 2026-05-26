import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import MagicLinkController from '@/actions/App/Http/Controllers/Auth/MagicLinkController';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';

type Props = {
    status?: string;
};

export default function MagicLink({ status }: Props) {
    return (
        <>
            <Head title="Email me a sign-in link" />

            {status && (
                <div
                    className="mb-4 text-center text-sm font-medium text-green-600"
                    data-test="magic-link-status"
                >
                    {status}
                </div>
            )}

            <div className="space-y-6">
                <Form {...MagicLinkController.store.form()} resetOnSuccess>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="email@example.com"
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-magic-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    Email me a sign-in link
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <div className="space-x-1 text-center text-sm text-muted-foreground">
                    <span>Prefer a password? Return to</span>
                    <TextLink href={login()}>log in</TextLink>
                </div>
            </div>
        </>
    );
}

MagicLink.layout = {
    title: 'Sign in with a magic link',
    description:
        'Enter your email — we will send you a one-time sign-in link that expires in 15 minutes.',
};
