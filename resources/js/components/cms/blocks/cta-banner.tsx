import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { BlockComponentProps } from '../types';

type Attrs = {
    title: string;
    body?: string;
    primary_cta_label?: string;
    primary_cta_url?: string;
    secondary_cta_label?: string;
    secondary_cta_url?: string;
    background_url?: string | null;
};

export default function CtaBannerBlock({ block }: BlockComponentProps<Attrs>) {
    const {
        title,
        body,
        primary_cta_label,
        primary_cta_url,
        secondary_cta_label,
        secondary_cta_url,
        background_url,
    } = block.attrs;

    return (
        <section
            className="relative overflow-hidden py-20"
            style={background_url ? { backgroundImage: `url(${background_url})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
            data-test="block-cta-banner"
        >
            {background_url && <div className="absolute inset-0 bg-background/70 backdrop-blur-sm" aria-hidden />}
            <div className="relative mx-auto w-full max-w-4xl px-4 text-center md:px-6">
                <h2 className="text-3xl font-semibold md:text-4xl">{title}</h2>
                {body && <p className="mt-4 text-lg text-muted-foreground">{body}</p>}
                {(primary_cta_label || secondary_cta_label) && (
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
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
        </section>
    );
}
