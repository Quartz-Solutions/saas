import { MoreHorizontal } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type ActionItem = {
    label: ReactNode;
    icon?: ReactNode;
    onSelect?: () => void;
    disabled?: boolean;
    destructive?: boolean;
    hidden?: boolean;
    /** Optional test id for Playwright. */
    'data-test'?: string;
};

export type ActionsMenuProps = {
    items: ActionItem[];
    triggerLabel?: string;
    /** Optional leading items shown above the dropdown trigger (e.g. quick-action buttons). */
    leading?: ReactNode;
    align?: 'start' | 'end';
};

export function ActionsMenu({
    items,
    triggerLabel = 'Actions',
    leading,
    align = 'end',
}: ActionsMenuProps) {
    const visible = items.filter((i) => !i.hidden);
    const safe = visible.filter((i) => !i.destructive);
    const danger = visible.filter((i) => i.destructive);

    return (
        <div className="flex items-center gap-2">
            {leading}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm" data-test="actions-menu-trigger">
                        <MoreHorizontal className="size-4" />
                        {triggerLabel}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align={align} className="w-56">
                    <DropdownMenuLabel className="text-xs">Actions</DropdownMenuLabel>
                    {safe.map((item, i) => (
                        <DropdownMenuItem
                            key={`safe-${i}`}
                            onSelect={(e) => {
                                e.preventDefault();

                                if (!item.disabled) {
item.onSelect?.();
}
                            }}
                            disabled={item.disabled}
                            data-test={item['data-test']}
                        >
                            {item.icon}
                            {item.label}
                        </DropdownMenuItem>
                    ))}
                    {danger.length > 0 && safe.length > 0 && <DropdownMenuSeparator />}
                    {danger.map((item, i) => (
                        <DropdownMenuItem
                            key={`danger-${i}`}
                            onSelect={(e) => {
                                e.preventDefault();

                                if (!item.disabled) {
item.onSelect?.();
}
                            }}
                            disabled={item.disabled}
                            data-test={item['data-test']}
                            className={cn(
                                'text-destructive focus:bg-destructive/10 focus:text-destructive',
                            )}
                        >
                            {item.icon}
                            {item.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
