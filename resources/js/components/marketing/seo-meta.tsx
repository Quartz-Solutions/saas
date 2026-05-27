import { Head, usePage } from '@inertiajs/react';

type SeoDefaults = {
    site_name?: string;
    title_template?: string;
    description?: string;
    og_image_url?: string;
    robots_default?: string;
};

type Brand = {
    og_default_url?: string | null;
};

type Social = {
    twitter_handle?: string;
};

type SharedProps = {
    name: string;
    cmsGlobals?: {
        seo_defaults?: SeoDefaults;
        brand?: Brand;
        social?: Social;
    };
};

type Props = {
    title: string;
    description: string;
    type?: 'website' | 'article';
    canonical?: string;
    pageTitle?: string;
    noIndex?: boolean;
    /** Optional OG image override (URL). Defaults to brand.og_default_url. */
    ogImage?: string | null;
    /** Optional JSON-LD payload (Article, FAQPage, etc). */
    schemaOrg?: unknown;
};

/**
 * Marketing-page SEO meta block. Wrap once per public page. Fills in
 * defaults from `cmsGlobals.seo_defaults` so a superadmin can set
 * site-wide title templates, descriptions, OG images, and robots
 * directives once and have them used as fallbacks everywhere.
 */
export default function SeoMeta({
    title,
    description,
    type = 'website',
    canonical,
    pageTitle,
    noIndex,
    ogImage,
    schemaOrg,
}: Props) {
    const { url, props } = usePage<SharedProps>();
    const seoDefaults = props.cmsGlobals?.seo_defaults ?? {};
    const brand = props.cmsGlobals?.brand ?? {};
    const social = props.cmsGlobals?.social ?? {};

    const appUrl = (typeof window !== 'undefined' && window.location?.origin) || '';
    const fullUrl = canonical ?? (appUrl ? appUrl + url : url);

    const siteName = seoDefaults.site_name || props.name;
    const titleTemplate = seoDefaults.title_template || '{page} - {site}';
    const renderedPageTitle = (pageTitle ?? title)
        ? titleTemplate.replace('{page}', pageTitle ?? title).replace('{site}', siteName)
        : siteName;
    const renderedDescription = description || seoDefaults.description || '';
    const renderedOgImage = ogImage ?? brand.og_default_url ?? seoDefaults.og_image_url ?? null;
    const robots = noIndex ? 'noindex,nofollow' : (seoDefaults.robots_default || 'index,follow');

    return (
        <Head title={renderedPageTitle}>
            <meta name="description" content={renderedDescription} />
            <link rel="canonical" href={fullUrl} />
            <meta name="robots" content={robots} />

            <meta property="og:site_name" content={siteName} />
            <meta property="og:type" content={type} />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={renderedDescription} />
            <meta property="og:url" content={fullUrl} />
            {renderedOgImage && <meta property="og:image" content={renderedOgImage} />}

            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={renderedDescription} />
            {renderedOgImage && <meta name="twitter:image" content={renderedOgImage} />}
            {social.twitter_handle && <meta name="twitter:site" content={`@${social.twitter_handle}`} />}

            {!!schemaOrg && (
                <script type="application/ld+json">{JSON.stringify(schemaOrg)}</script>
            )}
        </Head>
    );
}
