export interface User {
    id: number;
    name: string;
    email: string;
    role?: 'master' | 'admin' | 'user';
    permissions?: Record<string, boolean>;
    email_verified_at?: string;
    profile_photo_url?: string | null;
    two_factor_enabled?: boolean;
    two_factor_required?: boolean;
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
    flash?: { success?: string | null; warning?: string | null; recovery_codes?: string[] | null };
};
