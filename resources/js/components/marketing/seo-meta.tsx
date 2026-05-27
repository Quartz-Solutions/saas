import { Head, usePage } from '@inertiajs/react';

type Props = {
    title: string;
    description: string;
    type?: 'website' | 'article';
    /** Defaults to the current page URL. */
    canonical?: string;
    /** Override the default <title> tag (defaults to `title`). */
    pageTitle?: string;
    noIndex?: boolean;
};

/**
 * Marketing-page SEO meta block. Wrap once per public page (home,
 * pricing, docs, legal). Sets <title>, meta description, canonical URL,
 * Open Graph (Facebook/LinkedIn) tags, and Twitter card tags from a
 * single source of truth.
 */
export default function SeoMeta({
    title,
    description,
    type = 'website',
    canonical,
    pageTitle,
    noIndex,
}: Props) {
    const { url } = usePage();
    const appUrl = (typeof window !== 'undefined' && window.location?.origin) || '';
    const fullUrl = canonical ?? (appUrl ? appUrl + url : url);

    return (
        <Head title={pageTitle ?? title}>
            <meta name="description" content={description} />
            <link rel="canonical" href={fullUrl} />
            {noIndex && <meta name="robots" content="noindex,nofollow" />}

            <meta property="og:type" content={type} />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={fullUrl} />

            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
        </Head>
    );
}
