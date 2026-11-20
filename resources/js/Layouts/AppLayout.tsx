import type { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Alert from '../Components/Alert';
import type { OrganizationSummary, SharedProps } from '../types';

interface AppLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

export default function AppLayout({ title, description, children }: AppLayoutProps) {
    const { auth, status, organizations } = usePage<SharedProps>().props;

    return (
        <div className="min-h-full">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3">
                    <div className="flex items-center gap-6">
                        <Link href="/" className="text-sm font-semibold tracking-tight text-slate-900">
                            DeployFlow
                        </Link>

                        <OrganizationSwitcher organizations={organizations} />

                        {auth.user && (
                            <nav aria-label="Main">
                                <Link
                                    href="/organizations"
                                    className="text-sm text-slate-600 hover:text-slate-900"
                                >
                                    Organizations
                                </Link>
                            </nav>
                        )}
                    </div>

                    {auth.user && (
                        <div className="flex items-center gap-4">
                            <Link
                                href="/settings/profile"
                                className="hidden text-sm text-slate-600 hover:text-slate-900 sm:inline"
                            >
                                Profile
                            </Link>

                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="text-sm font-medium text-slate-600 hover:text-slate-900"
                            >
                                Log out
                            </Link>
                        </div>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-6 py-10">
                <div className="mb-6">
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">{title}</h1>

                    {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
                </div>

                {status && (
                    <div className="mb-6">
                        <Alert>{status}</Alert>
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}

function OrganizationSwitcher({ organizations }: { organizations: OrganizationSummary[] }) {
    if (organizations.length === 0) {
        return null;
    }

    return (
        <select
            aria-label="Active organization"
            className="rounded-md border border-slate-300 bg-white px-2 py-1 text-sm text-slate-900"
            defaultValue=""
            onChange={(event) => {
                if (event.target.value !== '') {
                    window.location.href = `/organizations/${event.target.value}`;
                }
            }}
        >
            <option value="" disabled>
                Organization
            </option>

            {organizations.map((organization) => (
                <option key={organization.id} value={organization.slug}>
                    {organization.name}
                </option>
            ))}
        </select>
    );
}
