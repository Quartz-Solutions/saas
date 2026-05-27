import { usePage } from '@inertiajs/react';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import type { BlockComponentProps, CmsRefs, FaqRef } from '../types';

type Attrs = {
    title?: string;
    group_slug?: string;
};

type ShareProps = { cmsRefs?: CmsRefs };

export default function FaqBlock({ block }: BlockComponentProps<Attrs>) {
    const { cmsRefs } = usePage<ShareProps>().props;
    const group = block.attrs.group_slug ?? 'default';
    const items: FaqRef[] = cmsRefs?.faqs?.[group] ?? [];

    if (items.length === 0) {
return null;
}

    return (
        <section className="py-20" data-test="block-faq">
            <div className="mx-auto w-full max-w-3xl px-4 md:px-6">
                {block.attrs.title && (
                    <h2 className="mb-8 text-center text-3xl font-semibold md:text-4xl">{block.attrs.title}</h2>
                )}
                <Accordion type="single" collapsible className="w-full">
                    {items.map((faq) => (
                        <AccordionItem key={faq.id} value={`faq-${faq.id}`}>
                            <AccordionTrigger className="text-left">{faq.question}</AccordionTrigger>
                            <AccordionContent>
                                <div
                                    className="prose prose-sm prose-neutral max-w-none dark:prose-invert"
                                    dangerouslySetInnerHTML={{ __html: faq.answer_html }}
                                />
                            </AccordionContent>
                        </AccordionItem>
                    ))}
                </Accordion>
            </div>
        </section>
    );
}
