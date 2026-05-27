import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

/**
 * Score how strongly a candidate URL matches the current path.
 *
 * - exact match → length of candidate (so longer wins among exacts)
 * - current path is a sub-path of candidate (`/admin` matches `/admin/x`)
 *   → length of candidate
 * - no match → -1
 *
 * This lets us pick the most-specific matching item across sibling groups.
 * For example, when current = `/admin/cms/media`:
 *   - Admin → Overview `/admin` scores 6
 *   - CMS → Media `/admin/cms/media` scores 16
 * CMS wins → only CMS opens, Admin stays collapsed.
 */
function matchScore(currentPath: string, candidate: string): number {
    if (!candidate) return -1;
    const c = candidate.split('?')[0].replace(/\/+$/, '') || '/';
    const cur = currentPath.replace(/\/+$/, '') || '/';
    if (c === cur) return c.length;
    if (c === '/') return cur === '/' ? 1 : -1;
    if (cur.startsWith(c + '/')) return c.length;
    return -1;
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const { isCurrentUrl, currentUrl } = useCurrentUrl();

    // Determine the single best-matching child URL across the entire nav.
    // The group that owns that URL is the one we auto-open; siblings stay
    // closed even if they have a weaker (shorter) prefix match.
    const bestMatchKey = useMemo(() => {
        let best: { score: number; itemTitle: string; childUrl: string } = {
            score: 0,
            itemTitle: '',
            childUrl: '',
        };
        for (const item of items) {
            for (const child of item.children ?? []) {
                const url = toUrl(child.href);
                const s = matchScore(currentUrl, url);
                if (s > best.score) {
                    best = { score: s, itemTitle: item.title, childUrl: url };
                }
            }
        }
        return best;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentUrl, items]);

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <CollapsibleNavItem
                            key={item.title}
                            item={item}
                            isInsideGroup={bestMatchKey.itemTitle === item.title}
                            activeChildUrl={
                                bestMatchKey.itemTitle === item.title
                                    ? bestMatchKey.childUrl
                                    : null
                            }
                        />
                    ) : (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isCurrentUrl(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function CollapsibleNavItem({
    item,
    isInsideGroup,
    activeChildUrl,
}: {
    item: NavItem;
    isInsideGroup: boolean;
    activeChildUrl: string | null;
}) {
    const [open, setOpen] = useState(isInsideGroup);

    // Auto-open the group when the user navigates into one of its pages.
    // Manual close (via the trigger) still works because we only force-open;
    // we never force-close on navigation.
    useEffect(() => {
        if (isInsideGroup) setOpen(true);
    }, [isInsideGroup]);

    return (
        <Collapsible
            asChild
            open={open}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: item.title }}
                        isActive={isInsideGroup}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {(item.children ?? []).map((child) => {
                            const childUrl = toUrl(child.href);
                            return (
                                <SidebarMenuSubItem key={child.title}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={childUrl === activeChildUrl}
                                    >
                                        <Link href={child.href} prefetch>
                                            {child.icon && <child.icon />}
                                            <span>{child.title}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
