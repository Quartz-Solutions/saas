import { cn } from '@/lib/utils';
import type { BlockComponentProps } from '../types';

type Attrs = {
    provider?: 'youtube' | 'vimeo' | 'mux' | 'url';
    video_id?: string;
    aspect?: '16:9' | '4:3' | '1:1' | '21:9';
};

const ASPECT_CLASS: Record<NonNullable<Attrs['aspect']>, string> = {
    '16:9': 'aspect-video',
    '4:3': 'aspect-[4/3]',
    '1:1': 'aspect-square',
    '21:9': 'aspect-[21/9]',
};

function buildSrc({ provider, video_id }: Attrs): string | null {
    if (!video_id) {
return null;
}

    if (provider === 'youtube') {
return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(video_id)}`;
}

    if (provider === 'vimeo') {
return `https://player.vimeo.com/video/${encodeURIComponent(video_id)}`;
}

    if (provider === 'mux') {
return `https://stream.mux.com/${encodeURIComponent(video_id)}.m3u8`;
}

    if (provider === 'url') {
return video_id;
}

    return null;
}

export default function VideoBlock({ block }: BlockComponentProps<Attrs>) {
    const src = buildSrc(block.attrs);

    if (!src) {
return null;
}

    const aspect = ASPECT_CLASS[block.attrs.aspect ?? '16:9'];

    return (
        <section className="mx-auto w-full max-w-4xl px-4 py-8 md:px-6" data-test="block-video">
            <div className={cn('overflow-hidden rounded-md border border-border/40', aspect)}>
                <iframe
                    src={src}
                    title="Video"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowFullScreen
                    className="size-full"
                />
            </div>
        </section>
    );
}
