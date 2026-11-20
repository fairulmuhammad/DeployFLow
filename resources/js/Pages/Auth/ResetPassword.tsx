import type { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AuthLayout from '../../Layouts/AuthLayout';

interface ResetPasswordProps {
    token: string;
    email: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout title="Choose a new password" description="This link can be used once and expires after 60 minutes.">
            <Head title="Choose a new password" />

            <form onSubmit={submit} className="space-y-4">
                <input type="hidden" name="token" value={data.token} />

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
                    label="New password"
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
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                        onChange={(event) => setData('password', event.target.value)}
                    />
                </FormField>

                <FormField
                    label="Confirm new password"
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
                        Reset password
                    </PrimaryButton>
                </div>
            </form>
        </AuthLayout>
    );
}
