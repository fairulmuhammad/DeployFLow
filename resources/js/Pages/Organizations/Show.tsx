import type { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AppLayout from '../../Layouts/AppLayout';
import type {
    Invitation,
    Member,
    MemberRole,
    OrganizationDetail,
} from '../../types';

interface ShowProps {
    organization: OrganizationDetail;
    members: Member[];
    invitations: Invitation[];
    memberCount: number;
}

const ROLE_OPTIONS: MemberRole[] = ['admin', 'developer', 'viewer'];

export default function OrganizationShow({
    organization,
    members,
    invitations,
}: ShowProps) {
    const renameForm = useForm({
        name: organization.name,
    });

    const inviteForm = useForm({
        email: '',
        role: 'developer',
    });

    const transferForm = useForm({
        member_id: '',
    });

    const deleteForm = useForm({});

    const submitRename = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        renameForm.patch(`/organizations/${organization.slug}`, {
            preserveScroll: true,
        });
    };

    const submitInvite = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        inviteForm.post(`/organizations/${organization.slug}/invitations`, {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset(),
        });
    };

    const submitTransfer = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transferForm.post(`/organizations/${organization.slug}/transfer`, {
            preserveScroll: true,
        });
    };

    const revokeInvitation = (invitationId: string) => {
        if (window.confirm('Revoke this invitation?')) {
            deleteForm.delete(`/organizations/${organization.slug}/invitations/${invitationId}`, {
                preserveScroll: true,
            });
        }
    };

    const removeMember = (memberId: string) => {
        if (window.confirm('Remove this member?')) {
            deleteForm.delete(`/organizations/${organization.slug}/members/${memberId}`, {
                preserveScroll: true,
            });
        }
    };

    const archiveOrganization = () => {
        if (window.confirm('Archive this organization? This cannot be undone in v1.')) {
            deleteForm.delete(`/organizations/${organization.slug}`);
        }
    };

    const isOwner = organization.role === 'owner';
    const isAdmin = isOwner || organization.role === 'admin';

    return (
        <AppLayout title={organization.name} description={`Tenant boundary · ${organization.slug}`}>
            <Head title={organization.name} />

            <div className="space-y-8">
                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Organization</h2>

                    {isAdmin ? (
                        <form onSubmit={submitRename} className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField
                                label="Name"
                                htmlFor="org-name"
                                error={renameForm.errors.name}
                            >
                                <TextInput
                                    id="org-name"
                                    type="text"
                                    value={renameForm.data.name}
                                    required
                                    maxLength={120}
                                    onChange={(event) => renameForm.setData('name', event.target.value)}
                                />
                            </FormField>

                            <div className="flex items-end">
                                <PrimaryButton type="submit" disabled={renameForm.processing}>
                                    Rename
                                </PrimaryButton>
                            </div>
                        </form>
                    ) : (
                        <p className="mt-4 text-sm text-slate-600">{organization.name}</p>
                    )}

                    <p className="mt-4 text-xs text-slate-500">
                        Created{' '}
                        <time dateTime={organization.created_at ?? undefined}>
                            {organization.created_at
                                ? new Date(organization.created_at).toLocaleDateString()
                                : 'unknown'}
                        </time>
                    </p>

                    {isOwner && (
                        <div className="mt-6 border-t border-slate-100 pt-4">
                            <h3 className="text-sm font-semibold text-slate-900">Danger zone</h3>

                            <button
                                type="button"
                                className="mt-2 text-sm font-medium text-red-600 underline-offset-4 hover:underline"
                                onClick={archiveOrganization}
                            >
                                Archive organization
                            </button>
                        </div>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Members</h2>

                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full text-sm">
                            <caption className="sr-only">Organization members</caption>
                            <thead>
                                <tr className="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th scope="col" className="py-2 pr-4">Name</th>
                                    <th scope="col" className="py-2 pr-4">Email</th>
                                    <th scope="col" className="py-2 pr-4">Role</th>
                                    <th scope="col" className="py-2 pr-4">Joined</th>
                                    <th scope="col" className="py-2" aria-label="Actions" />
                                </tr>
                            </thead>
                            <tbody>
                                {members.map((member) => (
                                    <MemberRow
                                        key={member.id}
                                        member={member}
                                        organizationSlug={organization.slug}
                                        canManage={isAdmin}
                                        onRemove={() => removeMember(member.id)}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {isAdmin && (
                        <form onSubmit={submitInvite} className="mt-6 border-t border-slate-100 pt-4">
                            <h3 className="text-sm font-semibold text-slate-900">Invite a member</h3>

                            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <FormField
                                    label="Email"
                                    htmlFor="invite-email"
                                    error={inviteForm.errors.email}
                                >
                                    <TextInput
                                        id="invite-email"
                                        type="email"
                                        value={inviteForm.data.email}
                                        required
                                        onChange={(event) =>
                                            inviteForm.setData('email', event.target.value)
                                        }
                                    />
                                </FormField>

                                <FormField
                                    label="Role"
                                    htmlFor="invite-role"
                                    error={inviteForm.errors.role}
                                >
                                    <select
                                        id="invite-role"
                                        className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900"
                                        value={inviteForm.data.role}
                                        onChange={(event) => inviteForm.setData('role', event.target.value)}
                                    >
                                        {ROLE_OPTIONS.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>
                                </FormField>

                                <div className="flex items-end">
                                    <PrimaryButton type="submit" disabled={inviteForm.processing}>
                                        Send invitation
                                    </PrimaryButton>
                                </div>
                            </div>
                        </form>
                    )}
                </section>

                {invitations.length > 0 && (
                    <section className="rounded-xl border border-slate-200 bg-white p-6">
                        <h2 className="text-base font-semibold text-slate-900">Pending invitations</h2>

                        <ul className="mt-4 divide-y divide-slate-100">
                            {invitations.map((invitation) => (
                                <li
                                    key={invitation.id}
                                    className="flex items-center justify-between py-2 text-sm"
                                >
                                    <div>
                                        <span className="font-medium text-slate-900">
                                            {invitation.email}
                                        </span>
                                        <span className="ml-2 text-slate-500">{invitation.role}</span>
                                    </div>

                                    {isAdmin && (
                                        <button
                                            type="button"
                                            className="font-medium text-slate-600 underline-offset-4 hover:underline"
                                            onClick={() => revokeInvitation(invitation.id)}
                                        >
                                            Revoke
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {isOwner && members.length > 1 && (
                    <section className="rounded-xl border border-amber-200 bg-amber-50 p-6">
                        <h2 className="text-base font-semibold text-amber-900">Transfer ownership</h2>

                        <p className="mt-1 text-sm text-amber-800">
                            The new owner gains full control; the current owner becomes an admin.
                        </p>

                        <form onSubmit={submitTransfer} className="mt-4 flex flex-wrap items-end gap-4">
                            <FormField
                                label="New owner"
                                htmlFor="transfer-member"
                                error={transferForm.errors.member_id}
                            >
                                <select
                                    id="transfer-member"
                                    className="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 sm:w-64"
                                    value={transferForm.data.member_id}
                                    onChange={(event) =>
                                        transferForm.setData('member_id', event.target.value)
                                    }
                                >
                                    <option value="" disabled>
                                        Select a member
                                    </option>

                                    {members
                                        .filter((member) => !member.is_owner)
                                        .map((member) => (
                                            <option key={member.id} value={member.id}>
                                                {member.name ?? member.email}
                                            </option>
                                        ))}
                                </select>
                            </FormField>

                            <PrimaryButton type="submit" disabled={transferForm.processing}>
                                Transfer ownership
                            </PrimaryButton>
                        </form>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

function MemberRow({
    member,
    organizationSlug,
    canManage,
    onRemove,
}: {
    member: Member;
    organizationSlug: string;
    canManage: boolean;
    onRemove: () => void;
}) {
    const roleForm = useForm({
        role: member.role,
    });

    const changeRole = () => {
        roleForm.patch(`/organizations/${organizationSlug}/members/${member.id}`, {
            preserveScroll: true,
            onError: () => roleForm.setData('role', member.role),
        });
    };

    return (
        <tr className="border-b border-slate-100 last:border-none">
            <td className="py-2 pr-4 font-medium text-slate-900">{member.name ?? '—'}</td>
            <td className="py-2 pr-4 text-slate-600">{member.email ?? '—'}</td>
            <td className="py-2 pr-4 text-slate-600">
                {canManage && !member.is_owner ? (
                    <select
                        aria-label={`Role for ${member.name ?? member.email ?? 'member'}`}
                        className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm text-slate-900"
                        value={roleForm.data.role}
                        onChange={(event) => {
                            roleForm.setData('role', event.target.value as MemberRole);
                            changeRole();
                        }}
                    >
                        {ROLE_OPTIONS.map((role) => (
                            <option key={role} value={role}>
                                {role}
                            </option>
                        ))}
                    </select>
                ) : (
                    member.role
                )}
            </td>
            <td className="py-2 pr-4 text-slate-600">
                <time dateTime={member.created_at ?? undefined}>
                    {member.created_at
                        ? new Date(member.created_at).toLocaleDateString()
                        : '—'}
                </time>
            </td>
            <td className="py-2 text-right">
                {canManage && !member.is_owner && (
                    <button
                        type="button"
                        className="font-medium text-slate-600 underline-offset-4 hover:underline"
                        onClick={onRemove}
                    >
                        Remove
                    </button>
                )}
            </td>
        </tr>
    );
}
