import type { BlockComponentProps } from '../types';

type Attrs = {
    html?: string;
};

/**
 * Raw HTML block — admin-only escape hatch. The server should sanitise
 * stored HTML before reaching here; this component does not re-sanitise.
 */
export default function HtmlBlock({ block }: BlockComponentProps<Attrs>) {
    const html = block.attrs.html ?? '';

    if (!html) {
return null;
}

    return (
        <section
            className="mx-auto w-full max-w-3xl px-4 py-4 md:px-6"
            data-test="block-html"
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}
