import { cn } from '@/lib/utils';
import type { BlockComponentProps } from '../types';

type Attrs = {
    media_id?: number | null;
    src?: string | null;
    alt?: string;
    caption?: string;
    layout?: 'contained' | 'full' | 'narrow';
    align?: 'left' | 'center' | 'right';
};

export default function ImageBlock({ block }: BlockComponentProps<Attrs>) {
    const { src, alt = '', caption = '', layout = 'contained', align = 'center' } = block.attrs;

    if (!src) {
return null;
}

    const widthClass =
        layout === 'full' ? 'w-full' : layout === 'narrow' ? 'max-w-xl' : 'max-w-3xl';
    const alignClass =
        align === 'left' ? 'mr-auto' : align === 'right' ? 'ml-auto' : 'mx-auto';

    return (
        <figure
            className={cn('px-4 py-6 md:px-6', widthClass, alignClass)}
            data-test="block-image"
        >
            <img src={src} alt={alt} className="h-auto w-full rounded-md border border-border/40" loading="lazy" />
            {caption && (
                <figcaption className="mt-2 text-center text-sm text-muted-foreground">
                    {caption}
                </figcaption>
            )}
        </figure>
    );
}
