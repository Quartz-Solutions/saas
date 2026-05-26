import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import NotificationsController from '@/actions/App/Http/Controllers/Notifications/NotificationsController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { NotificationItem } from '@/types/auth';

export function NotificationBell() {
    const { auth } = usePage<{
        auth: {
            unreadNotificationsCount: number;
            notifications: NotificationItem[];
        };
    }>().props;

    const unread = auth?.unreadNotificationsCount ?? 0;
    const notifications = auth?.notifications ?? [];

    const handleMarkOne = (id: string) => {
        const { url, method } = NotificationsController.markRead(id);
        router.visit(url, {
            method,
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
        });
    };

    const handleMarkAll = () => {
        const { url, method } = NotificationsController.markAllRead();
        router.visit(url, {
            method,
            preserveScroll: true,
            preserveState: true,
            only: ['auth'],
        });
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label="Notifications"
                    data-test="notification-bell"
                >
                    <Bell className="size-4" />
                    {unread > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-4 min-w-4 rounded-full px-1 text-[10px] leading-none"
                            data-test="notification-bell-badge"
                        >
                            {unread > 99 ? '99+' : unread}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="end"
                className="w-80 p-0"
                data-test="notification-bell-content"
            >
                <div className="flex items-center justify-between border-b px-4 py-3">
                    <div className="text-sm font-semibold">Notifications</div>
                    {notifications.length > 0 && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 gap-1 text-xs"
                            onClick={handleMarkAll}
                            disabled={unread === 0}
                            data-test="notification-bell-mark-all"
                        >
                            <CheckCheck className="size-3" />
                            Mark all read
                        </Button>
                    )}
                </div>

                <div className="max-h-96 overflow-y-auto">
                    {notifications.length === 0 ? (
                        <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                            You&apos;re all caught up.
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {notifications.map((notification) => {
                                const isRead = notification.read_at !== null;
                                const title =
                                    (notification.data.title as string) ??
                                    'Notification';
                                const description = notification.data
                                    .description as string | null | undefined;

                                return (
                                    <li
                                        key={notification.id}
                                        className={cn(
                                            'flex items-start gap-3 px-4 py-3 text-sm transition-colors',
                                            !isRead && 'bg-accent/40',
                                        )}
                                        data-test="notification-bell-item"
                                        data-read={isRead ? 'true' : 'false'}
                                    >
                                        <div className="flex-1">
                                            <p className="font-medium leading-tight">
                                                {title}
                                            </p>
                                            {description && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {description}
                                                </p>
                                            )}
                                            {notification.created_at_human && (
                                                <p className="mt-1 text-[10px] uppercase tracking-wide text-muted-foreground">
                                                    {
                                                        notification.created_at_human
                                                    }
                                                </p>
                                            )}
                                        </div>
                                        {!isRead && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-6 w-6 shrink-0"
                                                aria-label="Mark as read"
                                                onClick={() =>
                                                    handleMarkOne(
                                                        notification.id,
                                                    )
                                                }
                                                data-test="notification-bell-mark-one"
                                            >
                                                <CheckCheck className="size-3" />
                                            </Button>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
