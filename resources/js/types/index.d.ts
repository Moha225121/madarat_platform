export interface User {
    id: number;
    name: string;
    email: string;
    role: 'job_seeker' | 'employer' | 'admin';
    phone?: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
};
