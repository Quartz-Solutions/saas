import { Head } from '@inertiajs/react';

type Props = {
    type: string;
    effectiveDate: string;
    companyName: string;
};

export default function TermsPage({ effectiveDate, companyName }: Props) {
    return (
        <>
            <Head title="Terms of Service">
                <meta
                    name="description"
                    content={`${companyName} terms of service — the rules for using the service.`}
                />
            </Head>

            <article
                className="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16"
                data-test="legal-terms-page"
            >
                <header className="mb-8 border-b border-border/60 pb-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Legal
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                        Terms of Service
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Effective {effectiveDate}
                    </p>
                </header>

                <div className="prose prose-neutral max-w-none dark:prose-invert">
                    <p>
                        Placeholder terms of service. Each project must replace this with
                        legal-reviewed copy specific to its jurisdiction and product.
                    </p>

                    <h2>1. Acceptance</h2>
                    <p>
                        By creating an account or using {companyName}, you agree to these
                        Terms.
                    </p>

                    <h2>2. The service</h2>
                    <p>
                        We provide a hosted software service on a subscription basis.
                        Features available to your account depend on your plan and may
                        change over time.
                    </p>

                    <h2>3. Your account</h2>
                    <ul>
                        <li>You are responsible for keeping credentials safe.</li>
                        <li>You must be old enough to enter a contract in your country.</li>
                        <li>One person or entity per account, unless we agree otherwise.</li>
                    </ul>

                    <h2>4. Acceptable use</h2>
                    <p>
                        Don't use the service to break the law, harass others, or compromise
                        the security of the platform or other users.
                    </p>

                    <h2>5. Payment & cancellation</h2>
                    <p>
                        Paid plans are billed in advance. You may cancel at any time;
                        cancellation takes effect at the end of the current billing period.
                    </p>

                    <h2>6. Termination</h2>
                    <p>
                        We may suspend or terminate accounts that violate these Terms.
                    </p>

                    <h2>7. Warranties & liability</h2>
                    <p>
                        The service is provided &ldquo;as is&rdquo;. To the maximum extent
                        permitted by law, we exclude all implied warranties and limit our
                        liability.
                    </p>

                    <h2>8. Changes</h2>
                    <p>
                        We may update these Terms. Continued use after changes means you
                        accept the updated Terms.
                    </p>

                    <h2>9. Contact</h2>
                    <p>
                        Questions? Email{' '}
                        <a href="mailto:legal@example.com">legal@example.com</a>.
                    </p>
                </div>
            </article>
        </>
    );
}
