import type { BlockComponentProps } from '../types';

type Attrs = {
    html?: string;
};

export default function RichTextBlock({ block }: BlockComponentProps<Attrs>) {
    const html = block.attrs.html ?? '';

    if (!html) {
return null;
}

    return (
        <section className="mx-auto w-full max-w-3xl px-4 py-6 md:px-6" data-test="block-rich-text">
            <div
                className="prose prose-neutral max-w-none dark:prose-invert"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </section>
    );
}
