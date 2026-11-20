export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at: string | null;
}

export type MemberRole = 'owner' | 'admin' | 'developer' | 'viewer';

export interface OrganizationSummary {
    id: string;
    name: string;
    slug: string;
    role: MemberRole | null;
    created_at?: string | null;
}

export interface OrganizationDetail {
    id: string;
    name: string;
    slug: string;
    role: MemberRole | null;
    created_at: string | null;
}

export interface Member {
    id: string;
    name: string | null;
    email: string | null;
    role: MemberRole;
    is_owner: boolean;
    can_manage?: boolean;
    created_at: string | null;
}

export interface Invitation {
    id: string;
    email: string;
    role: MemberRole;
    invited_by: string;
    expires_at: string | null;
    created_at: string | null;
}

export interface SharedProps {
    auth: {
        user: User | null;
    };
    status: string | null;
    organizations: OrganizationSummary[];
    [key: string]: unknown;
}
