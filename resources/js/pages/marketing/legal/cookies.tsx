import SeoMeta from '@/components/marketing/seo-meta';

type Props = {
    type: string;
    effectiveDate: string;
    companyName: string;
};

export default function CookiesPage({ effectiveDate, companyName }: Props) {
    return (
        <>
            <SeoMeta
                pageTitle="Cookie Policy"
                title={`Cookie Policy — ${companyName}`}
                description={`${companyName} cookie policy — what cookies we set and why.`}
            />

            <article
                className="mx-auto w-full max-w-3xl px-4 py-12 md:px-6 md:py-16"
                data-test="legal-cookies-page"
            >
                <header className="mb-8 border-b border-border/60 pb-6">
                    <p className="text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        Legal
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight md:text-4xl">
                        Cookie Policy
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Effective {effectiveDate}
                    </p>
                </header>

                <div className="prose prose-neutral max-w-none dark:prose-invert">
                    <p>
                        This is placeholder cookie policy copy. Replace it with text
                        reviewed for the jurisdictions where {companyName} operates.
                    </p>

                    <h2>1. What are cookies?</h2>
                    <p>
                        Cookies are small text files stored on your device by your browser
                        when you visit a website. They are used to remember preferences,
                        keep you signed in, and measure usage.
                    </p>

                    <h2>2. Cookies we use</h2>
                    <ul>
                        <li>
                            <strong>Essential</strong> — required for the service to work
                            (authentication, CSRF protection, sidebar state).
                        </li>
                        <li>
                            <strong>Preferences</strong> — remember your settings (light /
                            dark mode, language, cookie consent itself).
                        </li>
                        <li>
                            <strong>Analytics</strong> — usage measurement, only set after
                            you accept the cookie banner.
                        </li>
                    </ul>

                    <h2>3. Your choices</h2>
                    <p>
                        Use the cookie banner at the bottom of every page to accept or
                        reject non-essential cookies. You can also clear cookies via your
                        browser settings at any time.
                    </p>

                    <h2>4. Third-party cookies</h2>
                    <p>
                        Some service providers (payment processors, analytics) may set
                        their own cookies. Their use is governed by their respective
                        policies.
                    </p>

                    <h2>5. Contact</h2>
                    <p>
                        Questions? Email{' '}
                        <a href="mailto:privacy@example.com">privacy@example.com</a>.
                    </p>
                </div>
            </article>
        </>
    );
}
