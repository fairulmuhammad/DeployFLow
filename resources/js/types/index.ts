export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    status: string | null;
    [key: string]: unknown;
}
