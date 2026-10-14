import type { Auth } from '@/types/auth';
import type { NotificationsSummary } from '@/types/notifications';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            organization: { name: string; slug: string } | null;
            auth: Auth;
            notifications: NotificationsSummary | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
