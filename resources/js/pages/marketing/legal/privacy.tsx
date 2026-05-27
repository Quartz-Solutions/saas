import SeoMeta from '@/components/marketing/seo-meta';

type Props = {
    type: string;
    effectiveDate: string;
    companyName: string;
};

export default function PrivacyPage({ effectiveDate, companyName }: Props) {
    return (
        <>
            <SeoMeta
                pageTitle="Privacy Policy"
                title={`Privacy Policy — ${companyName}`}
                description={`${companyName} privacy policy — what we collect, how we use it, your rights.`}
            />

            <article
                className="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16"
                data-test="legal-privacy-page"
            >
                <header className="mb-8 border-b border-border/60 pb-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Legal
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                        Privacy Policy
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Effective {effectiveDate}
                    </p>
                </header>

                <div className="prose prose-neutral max-w-none dark:prose-invert">
                    <p>
                        This is placeholder privacy policy copy. Replace it before
                        launching {companyName} in production — preferably after a review
                        by qualified counsel.
                    </p>

                    <h2>1. Information we collect</h2>
                    <p>
                        We collect information you provide directly (account details,
                        billing information) and information automatically generated when
                        you use the service (logs, device metadata, usage events).
                    </p>

                    <h2>2. How we use information</h2>
                    <ul>
                        <li>To provide and maintain the service.</li>
                        <li>To process payments and prevent fraud.</li>
                        <li>To communicate with you about your account.</li>
                        <li>To improve and develop new features.</li>
                    </ul>

                    <h2>3. Sharing</h2>
                    <p>
                        We do not sell your personal data. We share information only with
                        service providers under data-processing agreements, when required
                        by law, or with your consent.
                    </p>

                    <h2>4. Your rights</h2>
                    <p>
                        Subject to applicable law (including GDPR and CCPA), you may
                        request access, correction, export, or deletion of your personal
                        data. Contact us at <a href="mailto:privacy@example.com">privacy@example.com</a>.
                    </p>

                    <h2>5. Retention</h2>
                    <p>
                        We retain account data for the duration of your account and a
                        short period afterwards as required by tax, accounting and
                        regulatory obligations.
                    </p>

                    <h2>6. Changes</h2>
                    <p>
                        We may update this policy. We will notify you of material changes
                        via email or an in-app notice.
                    </p>

                    <h2>7. Contact</h2>
                    <p>
                        For questions about this policy, email{' '}
                        <a href="mailto:privacy@example.com">privacy@example.com</a>.
                    </p>
                </div>
            </article>
        </>
    );
}
