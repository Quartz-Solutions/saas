import { usePage } from '@inertiajs/react';
import type { BlockComponentProps, CmsRefs, LogoRef } from '../types';

type Attrs = {
    title?: string;
    group_slug?: string;
};

type ShareProps = { cmsRefs?: CmsRefs };

export default function LogoCloudBlock({ block }: BlockComponentProps<Attrs>) {
    const { cmsRefs } = usePage<ShareProps>().props;
    const group = block.attrs.group_slug ?? 'default';
    const logos: LogoRef[] = cmsRefs?.logos?.[group] ?? [];

    if (logos.length === 0) {
return null;
}

    return (
        <section className="border-y border-border/40 bg-muted/10 py-12" data-test="block-logo-cloud">
            <div className="mx-auto w-full max-w-6xl px-4 md:px-6">
                {block.attrs.title && (
                    <p className="mb-8 text-center text-sm font-medium uppercase tracking-wide text-muted-foreground">
                        {block.attrs.title}
                    </p>
                )}
                <div className="grid grid-cols-2 items-center justify-items-center gap-8 md:grid-cols-4 lg:grid-cols-6">
                    {logos.map((logo) => (
                        <div key={logo.id} className="opacity-70 transition-opacity hover:opacity-100">
                            {logo.image_url ? (
                                <img src={logo.image_url} alt={logo.name} className="h-8 w-auto" loading="lazy" />
                            ) : (
                                <span className="text-sm font-medium">{logo.name}</span>
                            )}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
