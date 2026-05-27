import { router } from '@inertiajs/react';
import { useMemo } from 'react';
import { cn } from '@/lib/utils';

export type TabSpec = {
    value: string;
    label: string;
    badge?: number | string | null;
    danger?: boolean;
    hidden?: boolean;
};

export type TabBarProps = {
    tabs: TabSpec[];
    /** Current value. Caller is responsible for parsing it from the URL. */
    value: string;
    /** Query-string param used to drive the tab. */
    paramName?: string;
    /** Inertia "only" props to reload when switching tabs. */
    only?: string[];
    /** Called after navigation. */
    onChange?: (value: string) => void;
    className?: string;
};

export function TabBar({
    tabs,
    value,
    paramName = 'tab',
    only,
    onChange,
    className,
}: TabBarProps) {
    const visible = useMemo(() => tabs.filter((t) => !t.hidden), [tabs]);

    const handleSelect = (next: string) => {
        if (next === value) {
            return;
        }

        // Swap parent state immediately — tab switching is purely client-side.
        onChange?.(next);

        if (typeof window === 'undefined') {
            return;
        }

        // Only push a history entry; never re-fetch the page, since tab state
        // is local to the component. Passing `only` to `router.visit` would
        // trigger a partial reload that crashes when `only` is undefined.
        const url = new URL(window.location.href);

        if (next) {
            url.searchParams.set(paramName, next);
        } else {
            url.searchParams.delete(paramName);
        }

        if (only && only.length > 0) {
            router.visit(url.pathname + url.search, {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only,
            });
        } else {
            window.history.replaceState(
                window.history.state,
                '',
                url.pathname + url.search,
            );
        }
    };

    return (
        <div
            data-test="tab-bar"
            className={cn(
                'flex items-center gap-1 border-b -mx-1 overflow-x-auto',
                className,
            )}
            role="tablist"
        >
            {visible.map((tab) => {
                const active = tab.value === value;

                return (
                    <button
                        key={tab.value}
                        type="button"
                        role="tab"
                        aria-selected={active}
                        data-test={`tab-${tab.value}`}
                        onClick={() => handleSelect(tab.value)}
                        className={cn(
                            'relative inline-flex items-center gap-2 whitespace-nowrap px-3 py-2 text-sm font-medium transition-colors',
                            active
                                ? 'text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                            tab.danger && !active && 'text-destructive/80 hover:text-destructive',
                            tab.danger && active && 'text-destructive',
                        )}
                    >
                        {tab.label}
                        {tab.badge !== undefined && tab.badge !== null && (
                            <span
                                className={cn(
                                    'rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium',
                                    active && 'bg-foreground/10',
                                )}
                            >
                                {tab.badge}
                            </span>
                        )}
                        {active && (
                            <span
                                className={cn(
                                    'absolute -bottom-px left-0 right-0 h-0.5',
                                    tab.danger ? 'bg-destructive' : 'bg-foreground',
                                )}
                            />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
