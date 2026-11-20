import { Head, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';
import type { MemberRole } from '../../types';

interface MembersProps {
    organization: {
        id: string;
        slug: string;
        name: string;
        role: MemberRole | null;
    };
    members: {
        id: string;
        name: string | null;
        email: string | null;
        role: MemberRole;
        is_owner: boolean;
        can_manage?: boolean;
        created_at: string | null;
    }[];
}

export default function Members({ organization, members }: MembersProps) {
    return (
        <AppLayout
            title={`Members · ${organization.name}`}
            description={`${members.length} member${members.length === 1 ? '' : 's'}`}
        >
            <Head title={`Members · ${organization.name}`} />

            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white p-6">
                <table className="w-full text-sm">
                    <caption className="sr-only">Members of {organization.name}</caption>
                    <thead>
                        <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th scope="col" className="py-2 pr-4">Name</th>
                            <th scope="col" className="py-2 pr-4">Email</th>
                            <th scope="col" className="py-2 pr-4">Role</th>
                            <th scope="col" className="py-2">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.map((member) => (
                            <MemberRow
                                key={member.id}
                                member={member}
                                organizationSlug={organization.slug}
                            />
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}

function MemberRow({
    member,
    organizationSlug,
}: {
    member: MembersProps['members'][number];
    organizationSlug: string;
}) {
    const roleForm = useForm({
        role: member.role,
    });

    const ROLE_OPTIONS: MemberRole[] = ['admin', 'developer', 'viewer'];

    return (
        <tr className="border-b border-slate-100 last:border-none">
            <td className="py-2 pr-4 font-medium text-slate-900">{member.name ?? '—'}</td>
            <td className="py-2 pr-4 text-slate-600">{member.email ?? '—'}</td>
            <td className="py-2 pr-4">
                <select
                    aria-label={`Role for ${member.name ?? member.email ?? 'member'}`}
                    className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm text-slate-900"
                    value={roleForm.data.role}
                    disabled={member.is_owner}
                    onChange={(event) => {
                        roleForm.setData('role', event.target.value as MemberRole);
                        roleForm.patch(`/organizations/${organizationSlug}/members/${member.id}`, {
                            preserveScroll: true,
                            onError: () => roleForm.setData('role', member.role),
                        });
                    }}
                >
                    {ROLE_OPTIONS.map((option) => (
                        <option key={option} value={option}>
                            {option}
                        </option>
                    ))}
                </select>
            </td>
            <td className="py-2 text-slate-600">
                <time dateTime={member.created_at ?? undefined}>
                    {member.created_at ? new Date(member.created_at).toLocaleDateString() : '—'}
                </time>
            </td>
        </tr>
    );
}
