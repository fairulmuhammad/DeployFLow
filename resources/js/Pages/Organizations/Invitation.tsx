import { Head, Link } from '@inertiajs/react';
import PrimaryButton from '../../Components/PrimaryButton';
import AuthLayout from '../../Layouts/AuthLayout';

interface InvitationProps {
    reason: 'unknown' | 'already-accepted' | 'expired' | 'email-mismatch' | null;
    organizationSlug: string | null;
}

const COPY: Record<string, { title: string; body: string }> = {
    unknown: {
        title: 'Invitation not found',
        body: 'This invitation link does not exist or was already used. Ask an administrator to send a new one.',
    },
    'already-accepted': {
        title: 'Invitation already accepted',
        body: 'This invitation was already used. Sign in to continue.',
    },
    expired: {
        title: 'Invitation expired',
        body: 'This invitation is no longer valid. Ask an administrator to send a new one.',
    },
    'email-mismatch': {
        title: 'Signed in with the wrong email',
        body: 'Your account email does not match the invited address. Sign out and sign in with the invited email.',
    },
};

export default function Invitation({ reason, organizationSlug }: InvitationProps) {
    const copy = reason !== null ? COPY[reason] : undefined;

    return (
        <AuthLayout
            title={copy?.title ?? 'Invitation'}
            description={copy?.body ?? 'You have an invitation to join an organization.'}
        >
            <Head title="Invitation" />

            {reason === null && organizationSlug !== null && (
                <div className="space-y-4">
                    <p className="text-sm text-slate-600">
                        Sign in with the invited email address to accept.
                    </p>

                    <div className="flex flex-wrap items-center gap-3">
                        <Link href="/login" as="button">
                            <PrimaryButton type="button">Sign in</PrimaryButton>
                        </Link>

                        <Link
                            href="/register"
                            className="text-sm font-medium text-slate-600 underline-offset-4 hover:underline"
                        >
                            Create an account
                        </Link>
                    </div>
                </div>
            )}

            {reason !== null && organizationSlug !== null && (
                <div className="mt-6">
                    <Link
                        href={`/organizations/${organizationSlug}`}
                        className="text-sm font-medium text-slate-600 underline-offset-4 hover:underline"
                    >
                        Go to {organizationSlug}
                    </Link>
                </div>
            )}
        </AuthLayout>
    );
}
