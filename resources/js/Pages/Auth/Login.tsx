import type { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import Alert from '../../Components/Alert';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AuthLayout from '../../Layouts/AuthLayout';

interface LoginProps {
    status?: string | null;
}

export default function Login({ status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Sign in" description="Use your DeployFlow account to continue.">
            <Head title="Sign in" />

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

                <FormField label="Password" htmlFor="password" error={errors.password}>
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                </FormField>

                <div className="flex items-center justify-between">
                    <Link
                        href="/forgot-password"
                        className="text-sm text-slate-600 underline-offset-4 hover:text-slate-900 hover:underline"
                    >
                        Forgot your password?
                    </Link>

                    <PrimaryButton type="submit" disabled={processing}>
                        Sign in
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-6 text-sm text-slate-600">
                No account?{' '}
                <Link href="/register" className="font-medium text-slate-900 underline-offset-4 hover:underline">
                    Create one
                </Link>
                .
            </p>
        </AuthLayout>
    );
}
