import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { read, readAll } from '@/routes/notifications';

/**
 * Bell with the unread count; opening a notification marks it read and shows its ticket.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    const { unreadCount, recent } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`Notifications (${unreadCount} unread)`}
                >
                    <Bell className="size-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    Notifications
                    {unreadCount > 0 && (
                        <button
                            type="button"
                            className="text-xs font-normal text-muted-foreground underline-offset-4 hover:underline"
                            onClick={() =>
                                router.post(
                                    readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mark all as read
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {recent.length === 0 ? (
                    <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        No notifications yet.
                    </p>
                ) : (
                    recent.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            onSelect={() =>
                                router.post(read.url(notification.id))
                            }
                            className="flex flex-col items-start gap-0.5"
                        >
                            <span
                                className={
                                    notification.read_at
                                        ? 'text-muted-foreground'
                                        : 'font-medium'
                                }
                            >
                                {notification.data.text}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {new Date(
                                    notification.created_at,
                                ).toLocaleString()}
                            </span>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
