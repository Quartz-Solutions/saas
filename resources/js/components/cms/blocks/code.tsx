import type { BlockComponentProps } from '../types';

type Attrs = {
    language?: string;
    code?: string;
    filename?: string;
};

export default function CodeBlock({ block }: BlockComponentProps<Attrs>) {
    const { language = 'bash', code = '', filename = '' } = block.attrs;

    if (!code) {
return null;
}

    return (
        <section className="mx-auto w-full max-w-3xl px-4 py-4 md:px-6" data-test="block-code">
            {filename && (
                <div className="rounded-t-md border border-b-0 border-border/60 bg-muted/40 px-3 py-1 font-mono text-xs text-muted-foreground">
                    {filename}
                </div>
            )}
            <pre className={`overflow-x-auto rounded-md ${filename ? 'rounded-t-none' : ''} bg-muted p-4 text-sm`}>
                <code className={`language-${language}`}>{code}</code>
            </pre>
        </section>
    );
}
