import type { BlockComponentProps } from '../types';

type StatItem = {
    label: string;
    value: string;
    suffix?: string;
};

type Attrs = {
    items?: StatItem[];
};

export default function StatsBlock({ block }: BlockComponentProps<Attrs>) {
    const items = block.attrs.items ?? [];

    if (items.length === 0) {
return null;
}

    return (
        <section className="border-y border-border/40 bg-muted/10 py-12" data-test="block-stats">
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                <div className="grid grid-cols-2 gap-6 text-center md:grid-cols-4">
                    {items.map((item, idx) => (
                        <div key={`${item.label}-${idx}`}>
                            <div className="text-3xl font-semibold md:text-4xl">
                                {item.value}
                                {item.suffix && <span className="text-primary">{item.suffix}</span>}
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">{item.label}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
