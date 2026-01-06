export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    department: string;
    role: string;
    is_active: boolean;
    deactivated_at?: string;
    reason?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
