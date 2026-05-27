import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type SidebarItem = { label: string; url: string };
type SidebarColumn = { title: string; items: SidebarItem[] };

type DocsRef = { slug: string; title: string; meta_description?: string | null };

type SharedProps = {
    cmsGlobals?: {
        docs_sidebar?: { columns?: SidebarColumn[] };
    };
};

type Props = {
    /** Fallback list when no docs_sidebar global is configured (auto from all published docs). */
    fallbackDocs?: DocsRef[];
    /** Currently-viewed doc slug — used to highlight the active item. */
    activeSlug?: string;
};

/**
 * Left-rail navigation for /docs and /docs/{slug}.
 *
 * Reads `cmsGlobals.docs_sidebar.columns` (managed at
 * /admin/cms/globals/docs_sidebar). If the global is empty, falls back to
 * a single flat list of every published docs page alphabetically.
 */
export default function DocsSidebar({ fallbackDocs = [], activeSlug }: Props) {
    const { props } = usePage<SharedProps>();
    const configured = props.cmsGlobals?.docs_sidebar?.columns ?? [];

    // Filter out empty columns / empty items so partial config still works.
    const columns = configured
        .map((c) => ({ ...c, items: (c.items ?? []).filter((i) => i.label && i.url) }))
        .filter((c) => c.items.length > 0);

    const useFallback = columns.length === 0;

    return (
        <nav
            className="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto pr-3"
            data-test="docs-sidebar"
            aria-label="Docs navigation"
        >
            {useFallback ? (
                <SidebarGroup
                    title="Documentation"
                    items={fallbackDocs.map((d) => ({ label: d.title, url: `/docs/${d.slug}` }))}
                    activeSlug={activeSlug}
                />
            ) : (
                <div className="space-y-6">
                    {columns.map((column) => (
                        <SidebarGroup
                            key={column.title}
                            title={column.title}
                            items={column.items}
                            activeSlug={activeSlug}
                        />
                    ))}
                </div>
            )}
        </nav>
    );
}

function SidebarGroup({
    title,
    items,
    activeSlug,
}: {
    title: string;
    items: SidebarItem[];
    activeSlug?: string;
}) {
    return (
        <div>
            <h3 className="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {title}
            </h3>
            <ul className="space-y-0.5 text-sm">
                {items.map((item) => {
                    const isActive =
                        activeSlug !== undefined &&
                        (item.url === `/docs/${activeSlug}` || item.url.endsWith(`/${activeSlug}`));
                    const isExternal = /^https?:\/\//i.test(item.url);

                    const className = cn(
                        'flex items-center gap-1 rounded-md px-3 py-1.5 transition-colors',
                        isActive
                            ? 'bg-primary/10 text-primary font-medium'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                    );

                    return (
                        <li key={item.url}>
                            {isExternal ? (
                                <a
                                    href={item.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className={className}
                                >
                                    {item.label}
                                </a>
                            ) : (
                                <Link href={item.url} className={className}>
                                    {isActive && <ChevronRight className="size-3 shrink-0" />}
                                    <span className="truncate">{item.label}</span>
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
