import type { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Alert from '../Components/Alert';
import type { SharedProps } from '../types';

interface AppLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

export default function AppLayout({ title, description, children }: AppLayoutProps) {
    const { auth, status } = usePage<SharedProps>().props;

    return (
        <div className="min-h-full">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-6 py-3">
                    <div className="flex items-center gap-6">
                        <Link href="/" className="text-sm font-semibold tracking-tight text-slate-900">
                            DeployFlow
                        </Link>

                        {auth.user && (
                            <nav aria-label="Main">
                                <Link
                                    href="/settings/profile"
                                    className="text-sm text-slate-600 hover:text-slate-900"
                                >
                                    Profile
                                </Link>
                            </nav>
                        )}
                    </div>

                    {auth.user && (
                        <div className="flex items-center gap-4">
                            <span className="hidden text-sm text-slate-500 sm:inline">{auth.user.email}</span>

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
