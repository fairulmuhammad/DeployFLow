import { Head, Link, usePage } from '@inertiajs/react';
import Alert from '../Components/Alert';
import type { SharedProps } from '../types';

interface HomeProps {
    laravelVersion: string;
    phpVersion: string;
}

export default function Home({ laravelVersion, phpVersion }: HomeProps) {
    const { auth, status } = usePage<SharedProps>().props;

    return (
        <>
            <Head title="Overview" />

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-3">
                    <span className="text-sm font-semibold tracking-tight text-slate-900">DeployFlow</span>

                    <nav aria-label="Account" className="flex items-center gap-4 text-sm">
                        {auth.user ? (
                            <>
                                <Link
                                    href="/settings/profile"
                                    className="text-slate-600 hover:text-slate-900"
                                >
                                    Profile
                                </Link>
                                <Link
                                    href="/logout"
                                    method="post"
                                    as="button"
                                    className="font-medium text-slate-600 hover:text-slate-900"
                                >
                                    Log out
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="text-slate-600 hover:text-slate-900">
                                    Sign in
                                </Link>
                                <Link
                                    href="/register"
                                    className="rounded-md bg-slate-900 px-3 py-1.5 font-medium text-white hover:bg-slate-800"
                                >
                                    Create account
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
            </header>

            <main className="mx-auto flex min-h-full max-w-4xl flex-col justify-center px-6 py-16">
                <p className="text-sm font-medium tracking-wide text-slate-500 uppercase">
                    Deployment control plane
                </p>

                <h1 className="mt-3 text-4xl font-semibold tracking-tight text-slate-900">DeployFlow</h1>

                <p className="mt-4 max-w-2xl text-lg text-slate-600">
                    Build a commit into an immutable image, deploy it blue-green, verify health, and keep
                    the previous release available for rollback.
                </p>

                {status && (
                    <div className="mt-6 max-w-2xl">
                        <Alert>{status}</Alert>
                    </div>
                )}

                <dl className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <dt className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                            Framework
                        </dt>
                        <dd className="mt-1 text-lg font-medium text-slate-900">
                            Laravel {laravelVersion}
                        </dd>
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <dt className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                            Runtime
                        </dt>
                        <dd className="mt-1 text-lg font-medium text-slate-900">PHP {phpVersion}</dd>
                    </div>

                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <dt className="text-xs font-medium tracking-wide text-slate-500 uppercase">
                            Status
                        </dt>
                        <dd className="mt-1 text-lg font-medium text-slate-900">M1 — Identity</dd>
                    </div>
                </dl>

                <p className="mt-10 text-sm text-slate-500">
                    Read <code className="rounded bg-slate-200 px-1.5 py-0.5">docs/ROADMAP.md</code> for what
                    comes next. Organizations and roles arrive in DF-006 and DF-007.
                </p>
            </main>
        </>
    );
}
