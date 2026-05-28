import { Form, Head } from '@inertiajs/react';
import { Github, Mail } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { create as magicLinkCreate } from '@/routes/auth/magic-link';
import { redirect as socialRedirect } from '@/routes/auth/social';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type SocialProvider = {
    label: string;
    icon: string;
};

type Props = {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    socialProviders?: Record<string, SocialProvider>;
};

function emailFromQuery(): string {
    if (typeof window === 'undefined') {
return '';
}

    return new URL(window.location.href).searchParams.get('email') ?? '';
}

function SocialIcon({ icon }: { icon: string }) {
    if (icon === 'github') {
return <Github className="size-4" />;
}

    if (icon === 'google') {
        return (
            <svg
                viewBox="0 0 24 24"
                className="size-4"
                aria-hidden
                fill="currentColor"
            >
                <path d="M21.35 11.1H12v2.9h5.35c-.23 1.45-1.66 4.25-5.35 4.25-3.22 0-5.85-2.67-5.85-5.95s2.63-5.95 5.85-5.95c1.83 0 3.05.78 3.75 1.45l2.55-2.45C16.7 3.9 14.55 3 12 3 6.97 3 3 6.97 3 12s3.97 9 9 9c5.2 0 8.65-3.65 8.65-8.8 0-.6-.07-1.05-.15-1.5z" />
            </svg>
        );
    }

    return <Mail className="size-4" />;
}

export default function Login({
    status,
    canResetPassword,
    canRegister,
    socialProviders = {},
}: Props) {
    const providerEntries = Object.entries(socialProviders);
    const prefillEmail = emailFromQuery();

    return (
        <>
            <Head title="Log in" />

            {providerEntries.length > 0 && (
                <div className="mb-6 grid gap-2">
                    {providerEntries.map(([key, provider]) => (
                        <Button
                            key={key}
                            type="button"
                            variant="outline"
                            asChild
                            className="w-full"
                            data-test={`social-${key}-button`}
                        >
                            <a href={socialRedirect({ provider: key }).url}>
                                <SocialIcon icon={provider.icon} />
                                Continue with {provider.label}
                            </a>
                        </Button>
                    ))}
                    <div className="my-2 flex items-center gap-3 text-xs uppercase tracking-wide text-muted-foreground">
                        <Separator className="flex-1" />
                        Or
                        <Separator className="flex-1" />
                    </div>
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                    defaultValue={prefillEmail}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            Forgot password?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Password"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">Remember me</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        <div className="text-center text-sm">
                            <TextLink href={magicLinkCreate()} tabIndex={6}>
                                Email me a sign-in link instead
                            </TextLink>
                        </div>

                        {canRegister && (
                            <div className="text-center text-sm text-muted-foreground">
                                Don't have an account?{' '}
                                <TextLink href={register()} tabIndex={5}>
                                    Sign up
                                </TextLink>
                            </div>
                        )}
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
