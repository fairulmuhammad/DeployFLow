import type { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Alert from '../../Components/Alert';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AuthLayout from '../../Layouts/AuthLayout';

interface ForgotPasswordProps {
    status?: string | null;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/forgot-password');
    };

    return (
        <AuthLayout
            title="Reset your password"
            description="Enter your email address and we will send a single-use reset link."
        >
            <Head title="Reset your password" />

            {status && (
                <div className="mb-4">
                    <Alert>{status}</Alert>
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Email address" htmlFor="email" error={errors.email}>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="email"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                </FormField>

                <div className="flex items-center justify-between">
                    <Link
                        href="/login"
                        className="text-sm text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                    >
                        Back to sign in
                    </Link>

                    <PrimaryButton type="submit" disabled={processing}>
                        Send reset link
                    </PrimaryButton>
                </div>
            </form>
        </AuthLayout>
    );
}
