/**
 * Shared CMS block types — used by the public renderer and (later) by the
 * admin block editor in M2.
 */

export type Block = {
    id: string;
    type: string;
    attrs: Record<string, unknown>;
    children?: Block[];
};

export type BlockComponentProps<A extends Record<string, unknown> = Record<string, unknown>> = {
    block: Block & { attrs: A };
};

/** Feature row referenced by feature_grid blocks (populated in M5). */
export type FeatureRef = {
    id: number;
    title: string;
    description: string | null;
    icon: string | null;
};

/** Testimonial row referenced by testimonials blocks (populated in M5). */
export type TestimonialRef = {
    id: number;
    quote: string;
    author_name: string;
    author_role: string | null;
    company: string | null;
    avatar_url: string | null;
    rating: number | null;
};

/** Logo row referenced by logo_cloud blocks (populated in M5). */
export type LogoRef = {
    id: number;
    name: string;
    url: string | null;
    image_url: string | null;
};

/** FAQ row referenced by faq blocks (populated in M5). */
export type FaqRef = {
    id: number;
    question: string;
    answer_html: string;
};

/** Plan row referenced by pricing blocks (already exists in plans table). */
export type PlanRef = {
    slug: string;
    name: string;
    description: string;
    price_cents: number;
    currency: string;
    interval: string;
    features: string[];
    cta: string;
    highlighted?: boolean;
};

/**
 * Inertia shares this map so blocks that reference reusable content
 * (feature_ids, testimonial_ids, plan_slugs, group_slug) can resolve
 * their references in a single pass without N+1 round-trips.
 * Populated in M5 / M10.
 */
export type CmsRefs = {
    features?: Record<number, FeatureRef>;
    testimonials?: Record<number, TestimonialRef>;
    logos?: Record<string, LogoRef[]>;
    faqs?: Record<string, FaqRef[]>;
    plans?: Record<string, PlanRef>;
};
