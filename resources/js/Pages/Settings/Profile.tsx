import type { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AppLayout from '../../Layouts/AppLayout';
import type { SharedProps } from '../../types';

interface ProfileProps {
    mustVerifyEmail: boolean;
}

export default function Profile({ mustVerifyEmail }: ProfileProps) {
    const { auth } = usePage<SharedProps>().props;
    const user = auth.user;

    const profileForm = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const resendForm = useForm({});

    const submitProfile = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        profileForm.patch('/settings/profile', { preserveScroll: true });
    };

    const submitPassword = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        passwordForm.patch('/settings/profile', {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
            onError: () => passwordForm.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AppLayout title="Profile" description="Update your account details and password.">
            <Head title="Profile" />

            <div className="space-y-8">
                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Account</h2>

                    {mustVerifyEmail && (
                        <div className="mt-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {user?.email_verified_at ? (
                                <span>Your email address is verified.</span>
                            ) : (
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <span>Your email address is not verified.</span>

                                    <button
                                        type="button"
                                        onClick={() => resendForm.post('/email/verification-notification')}
                                        disabled={resendForm.processing}
                                        className="font-medium underline underline-offset-4 disabled:opacity-50"
                                    >
                                        Resend verification link
                                    </button>
                                </div>
                            )}
                        </div>
                    )}

                    <form onSubmit={submitProfile} className="mt-6 space-y-4">
                        <FormField label="Name" htmlFor="name" error={profileForm.errors.name}>
                            <TextInput
                                id="name"
                                type="text"
                                name="name"
                                value={profileForm.data.name}
                                autoComplete="name"
                                required
                                aria-invalid={Boolean(profileForm.errors.name)}
                                aria-describedby={profileForm.errors.name ? 'name-error' : undefined}
                                onChange={(event) => profileForm.setData('name', event.target.value)}
                            />
                        </FormField>

                        <FormField
                            label="Email address"
                            htmlFor="email"
                            error={profileForm.errors.email}
                            help="Changing this address requires verifying it again."
                        >
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={profileForm.data.email}
                                autoComplete="email"
                                required
                                aria-invalid={Boolean(profileForm.errors.email)}
                                aria-describedby={profileForm.errors.email ? 'email-error' : undefined}
                                onChange={(event) => profileForm.setData('email', event.target.value)}
                            />
                        </FormField>

                        <div className="flex justify-end">
                            <PrimaryButton type="submit" disabled={profileForm.processing}>
                                Save account
                            </PrimaryButton>
                        </div>
                    </form>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Password</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Use at least 12 characters. You will stay signed in on this device.
                    </p>

                    <form onSubmit={submitPassword} className="mt-6 space-y-4">
                        <FormField
                            label="Current password"
                            htmlFor="current_password"
                            error={passwordForm.errors.current_password}
                        >
                            <TextInput
                                id="current_password"
                                type="password"
                                name="current_password"
                                value={passwordForm.data.current_password}
                                autoComplete="current-password"
                                aria-invalid={Boolean(passwordForm.errors.current_password)}
                                aria-describedby={
                                    passwordForm.errors.current_password ? 'current_password-error' : undefined
                                }
                                onChange={(event) => passwordForm.setData('current_password', event.target.value)}
                            />
                        </FormField>

                        <FormField
                            label="New password"
                            htmlFor="password"
                            error={passwordForm.errors.password}
                        >
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={passwordForm.data.password}
                                autoComplete="new-password"
                                aria-invalid={Boolean(passwordForm.errors.password)}
                                aria-describedby={passwordForm.errors.password ? 'password-error' : undefined}
                                onChange={(event) => passwordForm.setData('password', event.target.value)}
                            />
                        </FormField>

                        <FormField
                            label="Confirm new password"
                            htmlFor="password_confirmation"
                            error={passwordForm.errors.password_confirmation}
                        >
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={passwordForm.data.password_confirmation}
                                autoComplete="new-password"
                                aria-invalid={Boolean(passwordForm.errors.password_confirmation)}
                                aria-describedby={
                                    passwordForm.errors.password_confirmation
                                        ? 'password_confirmation-error'
                                        : undefined
                                }
                                onChange={(event) =>
                                    passwordForm.setData('password_confirmation', event.target.value)
                                }
                            />
                        </FormField>

                        <div className="flex justify-end">
                            <PrimaryButton type="submit" disabled={passwordForm.processing}>
                                Update password
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
