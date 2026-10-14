/**
 * In-app notifications, shared with every page (see HandleInertiaRequests).
 */
export type AppNotification = {
    id: string;
    data: {
        ticket_reference: string;
        text: string;
    };
    read_at: string | null;
    created_at: string;
};

export type NotificationsSummary = {
    unreadCount: number;
    recent: AppNotification[];
};
