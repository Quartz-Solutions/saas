export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type NotificationItem = {
    id: string;
    type: string;
    data: Record<string, unknown> & {
        event?: string;
        title?: string;
        description?: string | null;
    };
    read_at: string | null;
    created_at: string | null;
    created_at_human: string | null;
};

export type Auth = {
    user: User;
    unreadNotificationsCount: number;
    notifications: NotificationItem[];
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
