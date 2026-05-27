import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BlockComponentProps } from '../types';

type Attrs = {
    eyebrow?: string;
    title: string;
    body?: string;
    image_url?: string | null;
    image_side?: 'left' | 'right';
    cta_label?: string;
    cta_url?: string;
};

export default function FeatureSplitBlock({ block }: BlockComponentProps<Attrs>) {
    const { eyebrow, title, body, image_url, image_side = 'right', cta_label, cta_url } = block.attrs;

    return (
        <section className="py-16 md:py-24" data-test="block-feature-split">
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                <div className={cn('grid items-center gap-12 md:grid-cols-2', image_side === 'left' ? '[&>*:first-child]:md:order-2' : '')}>
                    <div>
                        {eyebrow && (
                            <p className="mb-3 text-sm font-medium uppercase tracking-wide text-muted-foreground">
                                {eyebrow}
                            </p>
                        )}
                        <h2 className="text-2xl font-semibold md:text-3xl">{title}</h2>
                        {body && (
                            <p className="mt-4 text-muted-foreground">{body}</p>
                        )}
                        {cta_label && cta_url && (
                            <div className="mt-6">
                                <Button asChild>
                                    <Link href={cta_url}>
                                        {cta_label} <ArrowRight className="ml-2 size-4" />
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </div>
                    <div>
                        {image_url ? (
                            <img src={image_url} alt="" className="w-full rounded-md border border-border/40" loading="lazy" />
                        ) : (
                            <div className="aspect-[4/3] w-full rounded-md border border-dashed border-border/60 bg-muted/30" />
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
