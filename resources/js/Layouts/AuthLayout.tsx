import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';

interface AuthLayoutProps {
    title: string;
    description?: string;
    children: ReactNode;
}

export default function AuthLayout({ title, description, children }: AuthLayoutProps) {
    return (
        <div className="flex min-h-full flex-col justify-center px-6 py-12">
            <div className="mx-auto w-full max-w-md">
                <Link href="/" className="block text-center text-lg font-semibold tracking-tight text-slate-900">
                    DeployFlow
                </Link>

                <div className="mt-8 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h1 className="text-xl font-semibold tracking-tight text-slate-900">{title}</h1>

                    {description && <p className="mt-2 text-sm text-slate-600">{description}</p>}

                    <div className="mt-6">{children}</div>
                </div>
            </div>
        </div>
    );
}
