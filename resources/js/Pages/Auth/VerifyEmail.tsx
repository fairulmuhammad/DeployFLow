import { Head, Link, useForm } from '@inertiajs/react';
import Alert from '../../Components/Alert';
import PrimaryButton from '../../Components/PrimaryButton';
import AuthLayout from '../../Layouts/AuthLayout';

interface VerifyEmailProps {
    status?: string | null;
}

export default function VerifyEmail({ status }: VerifyEmailProps) {
    const { post, processing } = useForm({});

    const resend = () => {
        post('/email/verification-notification');
    };

    return (
        <AuthLayout
            title="Verify your email address"
            description="We sent a signed link to your inbox. Open it to activate your account."
        >
            <Head title="Verify your email address" />

            {status && (
                <div className="mb-4">
                    <Alert>{status}</Alert>
                </div>
            )}

            <div className="flex flex-wrap items-center justify-between gap-4">
                <PrimaryButton type="button" onClick={resend} disabled={processing}>
                    Resend verification link
                </PrimaryButton>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="text-sm text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                >
                    Log out
                </Link>
            </div>
        </AuthLayout>
    );
}
