import { Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BlockComponentProps, CmsRefs } from '../types';

type Attrs = {
    eyebrow?: string;
    title: string;
    subtitle?: string;
    primary_cta_label?: string;
    primary_cta_url?: string;
    secondary_cta_label?: string;
    secondary_cta_url?: string;
    image_media_id?: number | null;
    image_url?: string | null;
    layout?: 'centered' | 'split-left' | 'split-right';
};

type ShareProps = { cmsRefs?: CmsRefs };

export default function HeroBlock({ block }: BlockComponentProps<Attrs>) {
    const { props } = usePage<ShareProps>();
    // Image URL resolution will be wired in M4 once media URLs are shared.
    void props;

    const {
        eyebrow,
        title,
        subtitle,
        primary_cta_label,
        primary_cta_url,
        secondary_cta_label,
        secondary_cta_url,
        image_url,
        layout = 'centered',
    } = block.attrs;

    if (layout === 'centered') {
        return (
            <section
                className="relative mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-28"
                data-test="block-hero"
            >
                <div className="mx-auto max-w-3xl text-center">
                    {eyebrow && (
                        <p className="mb-4 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                            {eyebrow}
                        </p>
                    )}
                    <h1 className="text-4xl font-semibold tracking-tight text-foreground md:text-6xl">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-6 text-lg text-muted-foreground md:text-xl">{subtitle}</p>
                    )}
                    {(primary_cta_label || secondary_cta_label) && (
                        <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                            {primary_cta_label && primary_cta_url && (
                                <Button asChild size="lg" data-test="hero-cta-primary">
                                    <Link href={primary_cta_url}>
                                        {primary_cta_label} <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            )}
                            {secondary_cta_label && secondary_cta_url && (
                                <Button asChild size="lg" variant="ghost" data-test="hero-cta-secondary">
                                    <Link href={secondary_cta_url}>{secondary_cta_label}</Link>
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </section>
        );
    }

    // split-left | split-right
    const imageOnRight = layout === 'split-right';

    return (
        <section
            className="relative mx-auto w-full max-w-6xl px-4 py-20 md:px-6 md:py-28"
            data-test="block-hero"
        >
            <div className={cn('grid items-center gap-8 md:grid-cols-2', imageOnRight ? '' : '[&>*:first-child]:order-2 md:[&>*:first-child]:order-1')}>
                <div>
                    {eyebrow && (
                        <p className="mb-4 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                            {eyebrow}
                        </p>
                    )}
                    <h1 className="text-3xl font-semibold tracking-tight text-foreground md:text-5xl">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="mt-4 text-lg text-muted-foreground">{subtitle}</p>
                    )}
                    {(primary_cta_label || secondary_cta_label) && (
                        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                            {primary_cta_label && primary_cta_url && (
                                <Button asChild size="lg">
                                    <Link href={primary_cta_url}>
                                        {primary_cta_label} <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            )}
                            {secondary_cta_label && secondary_cta_url && (
                                <Button asChild size="lg" variant="ghost">
                                    <Link href={secondary_cta_url}>{secondary_cta_label}</Link>
                                </Button>
                            )}
                        </div>
                    )}
                </div>
                <div>
                    {image_url ? (
                        <img src={image_url} alt="" className="size-full rounded-md object-cover" loading="lazy" />
                    ) : (
                        <div className="aspect-video w-full rounded-md border border-dashed border-border/60 bg-muted/30" />
                    )}
                </div>
            </div>
        </section>
    );
}
