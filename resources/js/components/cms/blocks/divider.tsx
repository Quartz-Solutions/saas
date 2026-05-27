import type { BlockComponentProps } from '../types';

type Attrs = {
    style?: 'line' | 'dotted' | 'space';
};

export default function DividerBlock({ block }: BlockComponentProps<Attrs>) {
    const style = block.attrs.style ?? 'line';

    if (style === 'space') {
        return <div className="h-10" data-test="block-divider-space" />;
    }

    return (
        <div className="mx-auto w-full max-w-3xl px-4 py-4 md:px-6" data-test="block-divider">
            <hr
                className={
                    style === 'dotted'
                        ? 'border-dotted border-border/60'
                        : 'border-border/60'
                }
            />
        </div>
    );
}
