import type { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AuthLayout from '../../Layouts/AuthLayout';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Create an account" description="DeployFlow is self-hosted. Your account is scoped to this instance.">
            <Head title="Create an account" />

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Name" htmlFor="name" error={errors.name}>
                    <TextInput
                        id="name"
                        type="text"
                        name="name"
                        value={data.name}
                        autoComplete="name"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.name)}
                        aria-describedby={errors.name ? 'name-error' : undefined}
                        onChange={(event) => setData('name', event.target.value)}
                    />
                </FormField>

                <FormField label="Email address" htmlFor="email" error={errors.email}>
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="email"
                        required
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? 'email-error' : undefined}
                        onChange={(event) => setData('email', event.target.value)}
                    />
                </FormField>

                <FormField
                    label="Password"
                    htmlFor="password"
                    error={errors.password}
                    help="At least 12 characters. Use a passphrase you do not reuse."
                >
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="new-password"
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                </FormField>

                <FormField
                    label="Confirm password"
                    htmlFor="password_confirmation"
                    error={errors.password_confirmation}
                >
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        required
                        aria-invalid={Boolean(errors.password_confirmation)}
                        aria-describedby={
                            errors.password_confirmation ? 'password_confirmation-error' : undefined
                        }
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                    />
                </FormField>

                <div className="flex justify-end">
                    <PrimaryButton type="submit" disabled={processing}>
                        Create account
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-6 text-sm text-slate-600">
                Already registered?{' '}
                <Link href="/login" className="font-medium text-slate-900 underline-offset-4 hover:underline">
                    Sign in
                </Link>
                .
            </p>
        </AuthLayout>
    );
}
