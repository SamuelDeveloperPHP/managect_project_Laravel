export interface User {
    id: number;
    name: string;
    email: string;
    role?: 'master' | 'admin' | 'user';
    permissions?: Record<string, boolean>;
    email_verified_at?: string;
}

export interface Company {
    id: number;
    name: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        company: Company | null;
    };
};
