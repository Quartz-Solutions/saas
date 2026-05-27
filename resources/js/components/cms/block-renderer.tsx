import type { ComponentType } from 'react';
import AnnouncementStripBlock from './blocks/announcement-strip';
import CodeBlock from './blocks/code';
import ContactFormBlock from './blocks/contact-form';
import CtaBannerBlock from './blocks/cta-banner';
import DividerBlock from './blocks/divider';
import FaqBlock from './blocks/faq';
import FeatureGridBlock from './blocks/feature-grid';
import FeatureSplitBlock from './blocks/feature-split';
import HeroBlock from './blocks/hero';
import HtmlBlock from './blocks/html';
import ImageBlock from './blocks/image';
import LogoCloudBlock from './blocks/logo-cloud';
import NewsletterBlock from './blocks/newsletter';
import PricingBlock from './blocks/pricing';
import RichTextBlock from './blocks/rich-text';
import StatsBlock from './blocks/stats';
import TestimonialsBlock from './blocks/testimonials';
import VideoBlock from './blocks/video';
import type { Block, BlockComponentProps } from './types';

/**
 * Block-type → React component map. New block types added in config/cms.php
 * must also have an entry here. Unknown types render a small warning in dev
 * and nothing in production.
 */
const REGISTRY: Record<string, ComponentType<BlockComponentProps>> = {
    rich_text: RichTextBlock as ComponentType<BlockComponentProps>,
    image: ImageBlock as ComponentType<BlockComponentProps>,
    video: VideoBlock as ComponentType<BlockComponentProps>,
    code: CodeBlock as ComponentType<BlockComponentProps>,
    divider: DividerBlock as ComponentType<BlockComponentProps>,
    html: HtmlBlock as ComponentType<BlockComponentProps>,
    hero: HeroBlock as ComponentType<BlockComponentProps>,
    feature_grid: FeatureGridBlock as ComponentType<BlockComponentProps>,
    feature_split: FeatureSplitBlock as ComponentType<BlockComponentProps>,
    pricing: PricingBlock as ComponentType<BlockComponentProps>,
    testimonials: TestimonialsBlock as ComponentType<BlockComponentProps>,
    logo_cloud: LogoCloudBlock as ComponentType<BlockComponentProps>,
    stats: StatsBlock as ComponentType<BlockComponentProps>,
    faq: FaqBlock as ComponentType<BlockComponentProps>,
    cta_banner: CtaBannerBlock as ComponentType<BlockComponentProps>,
    newsletter: NewsletterBlock as ComponentType<BlockComponentProps>,
    contact_form: ContactFormBlock as ComponentType<BlockComponentProps>,
    announcement_strip: AnnouncementStripBlock as ComponentType<BlockComponentProps>,
};

type Props = {
    blocks: Block[] | null | undefined;
};

export default function BlockRenderer({ blocks }: Props) {
    if (!blocks || blocks.length === 0) {
        return null;
    }

    return (
        <>
            {blocks.map((block) => {
                const Component = REGISTRY[block.type];

                if (!Component) {
                    if (import.meta.env.DEV) {
                        return (
                            <div
                                key={block.id}
                                className="my-4 rounded-md border border-dashed border-red-500 bg-red-500/5 p-3 text-sm text-red-600"
                            >
                                Unknown CMS block type: <code>{block.type}</code>
                            </div>
                        );
                    }

                    return null;
                }

                return <Component key={block.id} block={block} />;
            })}
        </>
    );
}
