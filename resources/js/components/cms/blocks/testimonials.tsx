import { usePage } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { BlockComponentProps, CmsRefs, TestimonialRef } from '../types';

type Attrs = {
    title?: string;
    layout?: 'single' | 'carousel' | 'grid';
    testimonial_ids?: number[];
};

type ShareProps = { cmsRefs?: CmsRefs };

function StarRating({ rating }: { rating: number | null }) {
    if (rating === null || rating <= 0) {
return null;
}

    return (
        <div className="mb-3 flex gap-0.5 text-amber-500">
            {Array.from({ length: 5 }).map((_, i) => (
                <Star key={i} className={cn('size-4', i < rating ? 'fill-current' : 'opacity-25')} />
            ))}
        </div>
    );
}

function Quote({ t }: { t: TestimonialRef }) {
    return (
        <Card className="h-full border-border/60" data-test="testimonial-card">
            <CardContent className="pt-6">
                <StarRating rating={t.rating} />
                <blockquote className="text-base text-foreground">&ldquo;{t.quote}&rdquo;</blockquote>
                <div className="mt-4 flex items-center gap-3">
                    {t.avatar_url ? (
                        <img src={t.avatar_url} alt="" className="size-10 rounded-full" />
                    ) : (
                        <div className="size-10 rounded-full bg-muted" />
                    )}
                    <div>
                        <p className="text-sm font-medium">{t.author_name}</p>
                        {(t.author_role || t.company) && (
                            <p className="text-xs text-muted-foreground">
                                {[t.author_role, t.company].filter(Boolean).join(' · ')}
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function TestimonialsBlock({ block }: BlockComponentProps<Attrs>) {
    const { cmsRefs } = usePage<ShareProps>().props;
    const items: TestimonialRef[] = (block.attrs.testimonial_ids ?? [])
        .map((id) => cmsRefs?.testimonials?.[id])
        .filter((t): t is TestimonialRef => Boolean(t));

    if (items.length === 0) {
return null;
}

    const layout = block.attrs.layout ?? 'grid';

    return (
        <section className="py-20" data-test="block-testimonials">
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                {block.attrs.title && (
                    <h2 className="mb-10 text-center text-3xl font-semibold md:text-4xl">{block.attrs.title}</h2>
                )}

                {layout === 'single' && items[0] && (
                    <div className="mx-auto max-w-2xl">
                        <Quote t={items[0]} />
                    </div>
                )}

                {layout === 'carousel' && (
                    <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2">
                        {items.map((t) => (
                            <div key={t.id} className="min-w-[280px] max-w-sm flex-1 snap-start">
                                <Quote t={t} />
                            </div>
                        ))}
                    </div>
                )}

                {layout === 'grid' && (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {items.map((t) => (
                            <Quote key={t.id} t={t} />
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
