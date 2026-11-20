import type { FormEvent } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import FormField from '../../Components/FormField';
import PrimaryButton from '../../Components/PrimaryButton';
import TextInput from '../../Components/TextInput';
import AppLayout from '../../Layouts/AppLayout';
import type { OrganizationSummary } from '../../types';

interface IndexProps {
    organizations: OrganizationSummary[];
}

export default function OrganizationsIndex({ organizations }: IndexProps) {
    const createForm = useForm({
        name: '',
        slug: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        createForm.post('/organizations', {
            onFinish: () => createForm.reset('name', 'slug'),
        });
    };

    return (
        <AppLayout
            title="Organizations"
            description="Tenant boundaries. Every application, environment, and deployment lives inside one."
        >
            <Head title="Organizations" />

            <div className="space-y-8">
                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Your organizations</h2>

                    {organizations.length === 0 ? (
                        <p className="mt-4 text-sm text-slate-600">
                            You have no organizations yet. Create one to get started.
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y divide-slate-100">
                            {organizations.map((organization) => (
                                <li key={organization.id} className="flex items-center justify-between py-3">
                                    <div>
                                        <Link
                                            href={`/organizations/${organization.slug}`}
                                            className="text-sm font-medium text-slate-900 hover:underline"
                                        >
                                            {organization.name}
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {organization.role ?? 'member'}
                                        </p>
                                    </div>

                                    <Link
                                        href={`/organizations/${organization.slug}`}
                                        className="text-sm font-medium text-slate-600 hover:text-slate-900"
                                    >
                                        Open
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-6">
                    <h2 className="text-base font-semibold text-slate-900">Create an organization</h2>

                    <form onSubmit={submit} className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="new-org-name"
                            error={createForm.errors.name}
                        >
                            <TextInput
                                id="new-org-name"
                                type="text"
                                name="name"
                                value={createForm.data.name}
                                required
                                maxLength={120}
                                onChange={(event) => createForm.setData('name', event.target.value)}
                            />
                        </FormField>

                        <FormField
                            label="Slug (optional)"
                            htmlFor="new-org-slug"
                            error={createForm.errors.slug}
                            help="Lowercase letters, numbers, and dashes. Defaults to the name."
                        >
                            <TextInput
                                id="new-org-slug"
                                type="text"
                                name="slug"
                                value={createForm.data.slug}
                                maxLength={80}
                                onChange={(event) => createForm.setData('slug', event.target.value)}
                            />
                        </FormField>

                        <div className="sm:col-start-2">
                            <PrimaryButton type="submit" disabled={createForm.processing}>
                                Create organization
                            </PrimaryButton>
                        </div>
                    </form>
                </section>
            </div>
        </AppLayout>
    );
}
