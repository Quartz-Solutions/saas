<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Block library
    |--------------------------------------------------------------------------
    |
    | The block-type catalog. Each entry becomes a `BlockType` registered in
    | `BlockTypeRegistry`. The order here matters for the admin block-picker
    | (within each `group`). Add new block types here and wire a matching
    | React component in `resources/js/components/cms/blocks/`.
    |
    | Shape:
    |   id            unique kebab-case identifier (block.type)
    |   label         admin UI label
    |   group         admin block-picker grouping
    |   icon          lucide icon name (admin-side)
    |   defaultAttrs  attributes set when a block is inserted
    |   rules         Laravel validation rules per attribute path
    |   description   admin help text
    |   isContainer   whether children[] is allowed
    |
    */
    'blocks' => [

        // ------ Content blocks ------------------------------------------

        [
            'id' => 'rich_text',
            'label' => 'Rich text',
            'group' => 'Content',
            'icon' => 'type',
            'defaultAttrs' => [
                'html' => '<p></p>',
            ],
            'rules' => [
                'html' => ['nullable', 'string'],
            ],
            'description' => 'Headings, paragraphs, lists, links. Inline formatting.',
        ],

        [
            'id' => 'image',
            'label' => 'Image',
            'group' => 'Content',
            'icon' => 'image',
            'defaultAttrs' => [
                'media_id' => null,
                'src' => null,
                'alt' => '',
                'caption' => '',
                'layout' => 'contained', // contained | full | narrow
                'align' => 'center',     // left | center | right
            ],
            'rules' => [
                'media_id' => ['nullable', 'integer'],
                'src' => ['nullable', 'string', 'max:2048'],
                'alt' => ['nullable', 'string', 'max:255'],
                'caption' => ['nullable', 'string', 'max:1000'],
                'layout' => ['nullable', 'in:contained,full,narrow'],
                'align' => ['nullable', 'in:left,center,right'],
            ],
            'description' => 'A single inline image with optional caption.',
        ],

        [
            'id' => 'video',
            'label' => 'Video embed',
            'group' => 'Content',
            'icon' => 'play-circle',
            'defaultAttrs' => [
                'provider' => 'youtube', // youtube | vimeo | mux | url
                'video_id' => '',
                'poster_media_id' => null,
                'aspect' => '16:9',
            ],
            'rules' => [
                'provider' => ['required', 'in:youtube,vimeo,mux,url'],
                'video_id' => ['required', 'string', 'max:255'],
                'poster_media_id' => ['nullable', 'integer'],
                'aspect' => ['nullable', 'in:16:9,4:3,1:1,21:9'],
            ],
            'description' => 'YouTube / Vimeo / Mux / direct-URL embed.',
        ],

        [
            'id' => 'code',
            'label' => 'Code block',
            'group' => 'Content',
            'icon' => 'code',
            'defaultAttrs' => [
                'language' => 'bash',
                'code' => '',
                'filename' => '',
            ],
            'rules' => [
                'language' => ['nullable', 'string', 'max:32'],
                'code' => ['nullable', 'string'],
                'filename' => ['nullable', 'string', 'max:255'],
            ],
            'description' => 'Syntax-highlighted code snippet.',
        ],

        [
            'id' => 'divider',
            'label' => 'Divider',
            'group' => 'Content',
            'icon' => 'minus',
            'defaultAttrs' => [
                'style' => 'line', // line | dotted | space
            ],
            'rules' => [
                'style' => ['nullable', 'in:line,dotted,space'],
            ],
            'description' => 'Horizontal separator.',
        ],

        [
            'id' => 'html',
            'label' => 'Raw HTML',
            'group' => 'Content',
            'icon' => 'file-code',
            'defaultAttrs' => [
                'html' => '',
            ],
            'rules' => [
                'html' => ['nullable', 'string'],
            ],
            'description' => 'Admin-only escape hatch. Sanitised on render.',
        ],

        // ------ Marketing blocks (referenced in M5) ---------------------

        [
            'id' => 'hero',
            'label' => 'Hero',
            'group' => 'Marketing',
            'icon' => 'sparkles',
            'defaultAttrs' => [
                'eyebrow' => '',
                'title' => 'Headline goes here',
                'subtitle' => 'Subtitle text describing the value proposition.',
                'primary_cta_label' => 'Get started',
                'primary_cta_url' => '/get-started',
                'secondary_cta_label' => 'See pricing',
                'secondary_cta_url' => '/pricing',
                'image_media_id' => null,
                'layout' => 'centered', // centered | split-left | split-right
            ],
            'rules' => [
                'eyebrow' => ['nullable', 'string', 'max:120'],
                'title' => ['required', 'string', 'max:240'],
                'subtitle' => ['nullable', 'string', 'max:1000'],
                'primary_cta_label' => ['nullable', 'string', 'max:64'],
                'primary_cta_url' => ['nullable', 'string', 'max:2048'],
                'secondary_cta_label' => ['nullable', 'string', 'max:64'],
                'secondary_cta_url' => ['nullable', 'string', 'max:2048'],
                'image_media_id' => ['nullable', 'integer'],
                'layout' => ['nullable', 'in:centered,split-left,split-right'],
            ],
            'description' => 'Top-of-page headline + CTAs.',
        ],

        [
            'id' => 'feature_grid',
            'label' => 'Feature grid',
            'group' => 'Marketing',
            'icon' => 'layout-grid',
            'defaultAttrs' => [
                'title' => 'Everything you need',
                'subtitle' => '',
                'columns' => 3,
                'feature_ids' => [],
            ],
            'rules' => [
                'title' => ['nullable', 'string', 'max:240'],
                'subtitle' => ['nullable', 'string', 'max:1000'],
                'columns' => ['nullable', 'integer', 'min:1', 'max:4'],
                'feature_ids' => ['nullable', 'array'],
                'feature_ids.*' => ['integer'],
            ],
            'description' => 'Grid of features pulled from the Features collection.',
        ],

        [
            'id' => 'feature_split',
            'label' => 'Feature split',
            'group' => 'Marketing',
            'icon' => 'columns-2',
            'defaultAttrs' => [
                'eyebrow' => '',
                'title' => 'A standout feature',
                'body' => '',
                'image_media_id' => null,
                'image_side' => 'right',
                'cta_label' => '',
                'cta_url' => '',
            ],
            'rules' => [
                'eyebrow' => ['nullable', 'string', 'max:120'],
                'title' => ['required', 'string', 'max:240'],
                'body' => ['nullable', 'string', 'max:5000'],
                'image_media_id' => ['nullable', 'integer'],
                'image_side' => ['nullable', 'in:left,right'],
                'cta_label' => ['nullable', 'string', 'max:64'],
                'cta_url' => ['nullable', 'string', 'max:2048'],
            ],
            'description' => 'Alternating image + text section.',
        ],

        [
            'id' => 'pricing',
            'label' => 'Pricing',
            'group' => 'Marketing',
            'icon' => 'credit-card',
            'defaultAttrs' => [
                'eyebrow' => '',
                'title' => 'Pricing',
                'subtitle' => 'Simple, predictable plans.',
                'plan_slugs' => [],
                'highlight_slug' => null,
            ],
            'rules' => [
                'eyebrow' => ['nullable', 'string', 'max:120'],
                'title' => ['nullable', 'string', 'max:240'],
                'subtitle' => ['nullable', 'string', 'max:1000'],
                'plan_slugs' => ['nullable', 'array'],
                'plan_slugs.*' => ['string'],
                'highlight_slug' => ['nullable', 'string'],
            ],
            'description' => 'Plan grid rendered from the plans table.',
        ],

        [
            'id' => 'testimonials',
            'label' => 'Testimonials',
            'group' => 'Marketing',
            'icon' => 'quote',
            'defaultAttrs' => [
                'title' => 'What customers say',
                'layout' => 'grid', // single | carousel | grid
                'testimonial_ids' => [],
            ],
            'rules' => [
                'title' => ['nullable', 'string', 'max:240'],
                'layout' => ['nullable', 'in:single,carousel,grid'],
                'testimonial_ids' => ['nullable', 'array'],
                'testimonial_ids.*' => ['integer'],
            ],
            'description' => 'Quotes from the Testimonials collection.',
        ],

        [
            'id' => 'logo_cloud',
            'label' => 'Logo cloud',
            'group' => 'Marketing',
            'icon' => 'badge-check',
            'defaultAttrs' => [
                'title' => 'Trusted by teams at',
                'group_slug' => 'default',
            ],
            'rules' => [
                'title' => ['nullable', 'string', 'max:240'],
                'group_slug' => ['nullable', 'string', 'max:120'],
            ],
            'description' => 'Customer / partner logos.',
        ],

        [
            'id' => 'stats',
            'label' => 'Stats',
            'group' => 'Marketing',
            'icon' => 'bar-chart-3',
            'defaultAttrs' => [
                'items' => [
                    ['label' => 'Active users', 'value' => '10,000', 'suffix' => '+'],
                ],
            ],
            'rules' => [
                'items' => ['nullable', 'array'],
                'items.*.label' => ['required_with:items', 'string', 'max:120'],
                'items.*.value' => ['required_with:items', 'string', 'max:64'],
                'items.*.suffix' => ['nullable', 'string', 'max:16'],
            ],
            'description' => 'Headline numbers (uptime, users, etc).',
        ],

        [
            'id' => 'faq',
            'label' => 'FAQ',
            'group' => 'Marketing',
            'icon' => 'help-circle',
            'defaultAttrs' => [
                'title' => 'Frequently asked questions',
                'group_slug' => 'default',
            ],
            'rules' => [
                'title' => ['nullable', 'string', 'max:240'],
                'group_slug' => ['nullable', 'string', 'max:120'],
            ],
            'description' => 'Accordion sourced from the FAQs collection.',
        ],

        [
            'id' => 'cta_banner',
            'label' => 'CTA banner',
            'group' => 'Marketing',
            'icon' => 'megaphone',
            'defaultAttrs' => [
                'title' => 'Ready to ship faster?',
                'body' => '',
                'primary_cta_label' => 'Get started',
                'primary_cta_url' => '/get-started',
                'secondary_cta_label' => '',
                'secondary_cta_url' => '',
                'background_media_id' => null,
            ],
            'rules' => [
                'title' => ['required', 'string', 'max:240'],
                'body' => ['nullable', 'string', 'max:1000'],
                'primary_cta_label' => ['nullable', 'string', 'max:64'],
                'primary_cta_url' => ['nullable', 'string', 'max:2048'],
                'secondary_cta_label' => ['nullable', 'string', 'max:64'],
                'secondary_cta_url' => ['nullable', 'string', 'max:2048'],
                'background_media_id' => ['nullable', 'integer'],
            ],
            'description' => 'Mid-page conversion banner.',
        ],

        [
            'id' => 'newsletter',
            'label' => 'Newsletter signup',
            'group' => 'Marketing',
            'icon' => 'mail',
            'defaultAttrs' => [
                'title' => 'Subscribe to updates',
                'body' => '',
                'success_message' => 'Thanks! Check your inbox to confirm.',
            ],
            'rules' => [
                'title' => ['nullable', 'string', 'max:240'],
                'body' => ['nullable', 'string', 'max:1000'],
                'success_message' => ['nullable', 'string', 'max:500'],
            ],
            'description' => 'Email capture wired to the configured newsletter provider.',
        ],

        [
            'id' => 'contact_form',
            'label' => 'Contact form',
            'group' => 'Marketing',
            'icon' => 'mail-plus',
            'defaultAttrs' => [
                'form_slug' => 'contact',
            ],
            'rules' => [
                'form_slug' => ['required', 'string', 'max:120'],
            ],
            'description' => 'Renders a form defined in CMS → Forms.',
        ],

        [
            'id' => 'announcement_strip',
            'label' => 'Announcement strip',
            'group' => 'Marketing',
            'icon' => 'flag',
            'defaultAttrs' => [
                'message' => '',
                'link_label' => '',
                'link_url' => '',
                'variant' => 'info', // info | success | warning
            ],
            'rules' => [
                'message' => ['required', 'string', 'max:500'],
                'link_label' => ['nullable', 'string', 'max:64'],
                'link_url' => ['nullable', 'string', 'max:2048'],
                'variant' => ['nullable', 'in:info,success,warning'],
            ],
            'description' => 'Page-local announcement bar (distinct from the site-wide one).',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved route names
    |--------------------------------------------------------------------------
    |
    | A CMS page can claim a reserved route name and be served instead of
    | the hardcoded controller. M5 will wire this for `home`, `pricing` etc.
    |
    */
    'reserved_routes' => [
        'home' => 'Landing page (/)',
        'pricing' => 'Pricing (/pricing)',
        'about' => 'About (/about)',
        'features' => 'Features (/features)',
        'contact' => 'Contact (/contact)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Globals — site-wide singletons
    |--------------------------------------------------------------------------
    |
    | Each entry is a singleton stored as one row in `cms_globals`. The
    | `fields` array describes the editable form. Public-facing data is
    | shared on every Inertia request via HandleInertiaRequests.
    |
    | Field types:
    |   text | textarea | url | email | color | image | select | switch |
    |   number | menu | columns
    |
    | Notes:
    |   `menu` is a tree-structured navigation (header / footer)
    |   `columns` is the footer column-list shape
    |   `image` will get a picker in M4 (today: text input for path)
    |
    */
    'globals' => [

        'brand' => [
            'label' => 'Brand & theme',
            'description' => 'Logo, favicon, colors. Used in the public layout and SEO/OG tags.',
            'fields' => [
                ['key' => 'logo_light_url', 'label' => 'Light-mode logo URL', 'type' => 'image'],
                ['key' => 'logo_dark_url', 'label' => 'Dark-mode logo URL', 'type' => 'image'],
                ['key' => 'favicon_url', 'label' => 'Favicon URL', 'type' => 'image'],
                ['key' => 'og_default_url', 'label' => 'Default OG image URL (1200×630)', 'type' => 'image'],
                ['key' => 'brand_color', 'label' => 'Brand color', 'type' => 'color'],
                ['key' => 'accent_color', 'label' => 'Accent color', 'type' => 'color'],
                ['key' => 'font_heading', 'label' => 'Heading font', 'type' => 'text'],
                ['key' => 'font_body', 'label' => 'Body font', 'type' => 'text'],
            ],
            'defaults' => [
                'logo_light_url' => null,
                'logo_dark_url' => null,
                'favicon_url' => null,
                'og_default_url' => null,
                'brand_color' => '#4f46e5',
                'accent_color' => '#10b981',
                'font_heading' => '',
                'font_body' => '',
            ],
        ],

        'header_menu' => [
            'label' => 'Header navigation',
            'description' => 'Public-site top nav. Drag items to reorder.',
            'fields' => [
                ['key' => 'items', 'label' => 'Items', 'type' => 'menu'],
            ],
            'defaults' => [
                'items' => [
                    ['label' => 'Features', 'url' => '/#features', 'target' => '_self', 'children' => []],
                    ['label' => 'Pricing', 'url' => '/pricing', 'target' => '_self', 'children' => []],
                    ['label' => 'Docs', 'url' => '/docs', 'target' => '_self', 'children' => []],
                ],
            ],
        ],

        'footer_menu' => [
            'label' => 'Footer navigation',
            'description' => 'Footer columns. Each column is a heading and a list of links.',
            'fields' => [
                ['key' => 'columns', 'label' => 'Columns', 'type' => 'columns'],
                ['key' => 'copyright_line', 'label' => 'Copyright line', 'type' => 'text'],
                ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea'],
            ],
            'defaults' => [
                'columns' => [
                    [
                        'title' => 'Product',
                        'items' => [
                            ['label' => 'Pricing', 'url' => '/pricing'],
                            ['label' => 'Docs', 'url' => '/docs'],
                            ['label' => 'Features', 'url' => '/#features'],
                        ],
                    ],
                    [
                        'title' => 'Legal',
                        'items' => [
                            ['label' => 'Privacy', 'url' => '/legal/privacy'],
                            ['label' => 'Terms', 'url' => '/legal/terms'],
                            ['label' => 'Cookies', 'url' => '/legal/cookies'],
                        ],
                    ],
                ],
                'copyright_line' => '© {year} {site}. All rights reserved.',
                'tagline' => '',
            ],
        ],

        'announcement' => [
            'label' => 'Announcement bar',
            'description' => 'Optional strip shown at the top of every public page.',
            'fields' => [
                ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'switch'],
                ['key' => 'message', 'label' => 'Message', 'type' => 'text'],
                ['key' => 'link_url', 'label' => 'Link URL', 'type' => 'url'],
                ['key' => 'link_label', 'label' => 'Link label', 'type' => 'text'],
                ['key' => 'variant', 'label' => 'Variant', 'type' => 'select', 'options' => ['info', 'success', 'warning']],
                ['key' => 'dismissible', 'label' => 'Dismissible', 'type' => 'switch'],
            ],
            'defaults' => [
                'enabled' => false,
                'message' => '',
                'link_url' => '',
                'link_label' => '',
                'variant' => 'info',
                'dismissible' => true,
            ],
        ],

        'analytics' => [
            'label' => 'Analytics',
            'description' => 'Tracking IDs injected on public pages only. Leave blank to skip.',
            'fields' => [
                ['key' => 'ga4_id', 'label' => 'Google Analytics 4 ID (G-…)', 'type' => 'text'],
                ['key' => 'plausible_domain', 'label' => 'Plausible domain', 'type' => 'text'],
                ['key' => 'posthog_key', 'label' => 'PostHog project key', 'type' => 'text'],
                ['key' => 'posthog_host', 'label' => 'PostHog host', 'type' => 'url'],
                ['key' => 'gtm_id', 'label' => 'Google Tag Manager ID (GTM-…)', 'type' => 'text'],
                ['key' => 'meta_pixel_id', 'label' => 'Meta Pixel ID', 'type' => 'text'],
                ['key' => 'hotjar_id', 'label' => 'Hotjar Site ID', 'type' => 'text'],
            ],
            'defaults' => [
                'ga4_id' => '',
                'plausible_domain' => '',
                'posthog_key' => '',
                'posthog_host' => '',
                'gtm_id' => '',
                'meta_pixel_id' => '',
                'hotjar_id' => '',
            ],
        ],

        'cookie_banner' => [
            'label' => 'Cookie banner',
            'description' => 'Consent prompt copy and policy link.',
            'fields' => [
                ['key' => 'enabled', 'label' => 'Enabled', 'type' => 'switch'],
                ['key' => 'message', 'label' => 'Message', 'type' => 'textarea'],
                ['key' => 'accept_label', 'label' => 'Accept label', 'type' => 'text'],
                ['key' => 'reject_label', 'label' => 'Reject label', 'type' => 'text'],
                ['key' => 'settings_label', 'label' => 'Settings label', 'type' => 'text'],
                ['key' => 'policy_url', 'label' => 'Policy URL', 'type' => 'url'],
            ],
            'defaults' => [
                'enabled' => true,
                'message' => 'We use cookies to improve your experience.',
                'accept_label' => 'Accept',
                'reject_label' => 'Reject',
                'settings_label' => 'Settings',
                'policy_url' => '/legal/cookies',
            ],
        ],

        'contact' => [
            'label' => 'Contact info',
            'description' => 'Used on the contact page, in the footer, and in LocalBusiness JSON-LD.',
            'fields' => [
                ['key' => 'company_name', 'label' => 'Company name', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Support email', 'type' => 'email'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                ['key' => 'hours', 'label' => 'Business hours', 'type' => 'text'],
                ['key' => 'support_url', 'label' => 'Support portal URL', 'type' => 'url'],
                ['key' => 'status_url', 'label' => 'Status page URL', 'type' => 'url'],
            ],
            'defaults' => [
                'company_name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'hours' => '',
                'support_url' => '',
                'status_url' => '',
            ],
        ],

        'social' => [
            'label' => 'Social handles',
            'description' => 'Linked from the footer and used in OG / Twitter cards.',
            'fields' => [
                ['key' => 'twitter', 'label' => 'Twitter / X URL', 'type' => 'url'],
                ['key' => 'linkedin', 'label' => 'LinkedIn URL', 'type' => 'url'],
                ['key' => 'github', 'label' => 'GitHub URL', 'type' => 'url'],
                ['key' => 'youtube', 'label' => 'YouTube URL', 'type' => 'url'],
                ['key' => 'facebook', 'label' => 'Facebook URL', 'type' => 'url'],
                ['key' => 'instagram', 'label' => 'Instagram URL', 'type' => 'url'],
                ['key' => 'twitter_handle', 'label' => 'Twitter handle (for cards, no @)', 'type' => 'text'],
            ],
            'defaults' => [
                'twitter' => '',
                'linkedin' => '',
                'github' => '',
                'youtube' => '',
                'facebook' => '',
                'instagram' => '',
                'twitter_handle' => '',
            ],
        ],

        'seo_defaults' => [
            'label' => 'SEO defaults',
            'description' => 'Fallback meta values used when a page leaves SEO fields blank.',
            'fields' => [
                ['key' => 'site_name', 'label' => 'Site name', 'type' => 'text'],
                ['key' => 'title_template', 'label' => 'Title template (e.g. {page} - {site})', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Default meta description', 'type' => 'textarea'],
                ['key' => 'og_image_url', 'label' => 'Default OG image URL', 'type' => 'image'],
                ['key' => 'robots_default', 'label' => 'Default robots directive', 'type' => 'text'],
            ],
            'defaults' => [
                'site_name' => '',
                'title_template' => '{page} - {site}',
                'description' => '',
                'og_image_url' => '',
                'robots_default' => 'index,follow',
            ],
        ],

        'docs_sidebar' => [
            'label' => 'Docs sidebar',
            'description' => 'Left-rail navigation shown on /docs and /docs/{slug}. Each column is a group; each item links to a doc by URL (e.g. /docs/cms-pages) or any external resource. Leave empty to auto-list every published docs page alphabetically.',
            'fields' => [
                ['key' => 'columns', 'label' => 'Groups', 'type' => 'columns'],
            ],
            'defaults' => [
                'columns' => [
                    [
                        'title' => 'Getting started',
                        'items' => [
                            ['label' => 'Overview', 'url' => '/docs/cms-overview'],
                            ['label' => 'Pages', 'url' => '/docs/cms-pages'],
                            ['label' => 'Block library', 'url' => '/docs/cms-blocks'],
                        ],
                    ],
                    [
                        'title' => 'Content',
                        'items' => [
                            ['label' => 'Media library', 'url' => '/docs/cms-media'],
                            ['label' => 'Reusable collections', 'url' => '/docs/cms-collections'],
                            ['label' => 'Blog', 'url' => '/docs/cms-blog'],
                        ],
                    ],
                    [
                        'title' => 'Site setup',
                        'items' => [
                            ['label' => 'Globals', 'url' => '/docs/cms-globals'],
                            ['label' => 'SEO', 'url' => '/docs/cms-seo'],
                            ['label' => 'i18n & localization', 'url' => '/docs/cms-i18n'],
                        ],
                    ],
                    [
                        'title' => 'Lead capture',
                        'items' => [
                            ['label' => 'Forms', 'url' => '/docs/cms-forms'],
                            ['label' => 'Newsletter', 'url' => '/docs/cms-newsletter'],
                        ],
                    ],
                    [
                        'title' => 'Operations',
                        'items' => [
                            ['label' => 'Redirects', 'url' => '/docs/cms-redirects'],
                            ['label' => 'Versions & preview', 'url' => '/docs/cms-versions-preview'],
                            ['label' => 'Cache & invalidation', 'url' => '/docs/cms-cache'],
                        ],
                    ],
                    [
                        'title' => 'Billing & admin',
                        'items' => [
                            ['label' => 'Plans', 'url' => '/docs/admin-plans'],
                            ['label' => 'Subscriptions', 'url' => '/docs/admin-subscriptions'],
                            ['label' => 'Checkout sessions', 'url' => '/docs/admin-checkout'],
                            ['label' => 'Payment gateways', 'url' => '/docs/admin-gateways'],
                            ['label' => 'App settings', 'url' => '/docs/admin-settings'],
                        ],
                    ],
                ],
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | i18n
    |--------------------------------------------------------------------------
    | Supported locales for CMS content. The first entry is the canonical
    | fallback when a requested locale isn't authored. Add codes here as
    | you launch new markets — SetCmsLocale middleware respects them.
    */
    'locales' => [
        'en', 'ar', 'fr', 'es', 'de',
    ],

    'default_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Newsletter
    |--------------------------------------------------------------------------
    |
    | Driver registry config. `provider` picks the active driver
    | (database | mailchimp | resend | convertkit). Each driver reads
    | its credentials from the nested sub-array.
    |
    | Drivers always also persist a local row in
    | `cms_newsletter_subscribers`, so the admin inbox stays in sync
    | regardless of which ESP is active.
    |
    */
    'newsletter' => [
        'provider' => env('CMS_NEWSLETTER_PROVIDER', 'database'),

        'mailchimp' => [
            'api_key' => env('MAILCHIMP_API_KEY'),
            'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
            'server' => env('MAILCHIMP_SERVER'),
            'double_opt_in' => (bool) env('MAILCHIMP_DOUBLE_OPT_IN', false),
        ],

        'resend' => [
            'api_key' => env('RESEND_API_KEY'),
            'audience_id' => env('RESEND_AUDIENCE_ID'),
        ],

        'convertkit' => [
            'api_key' => env('CONVERTKIT_API_KEY'),
            'form_id' => env('CONVERTKIT_FORM_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTLs (seconds)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'page_ttl' => 3600,
        'docs_index_ttl' => 3600,
        'globals_ttl' => 3600,
    ],

];
