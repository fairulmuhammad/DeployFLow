import { Head } from '@inertiajs/react';

interface HomeProps {
    laravelVersion: string;
    phpVersion: string;
}

export default function Home({ laravelVersion, phpVersion }: HomeProps) {
    return (
        <>
            <Head title="Overview" />

            <main className="mx-auto flex min-h-full max-w-4xl flex-col justify-center px-6 py-16">
                <p className="text-sm font-medium tracking-wide text-slate-500 uppercase">
                    Deployment control plane
                </p>

                <h1 className="mt-3 text-4xl font-semibold tracking-tight text-slate-900">
                    DeployFlow
                </h1>

                <p className="mt-4 max-w-2xl text-lg text-slate-600">
                    Build a commit into an immutable image, deploy it blue-green, verify health, and keep
                    the previous release available for rollback.
                </p>

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
                        <dd className="mt-1 text-lg font-medium text-slate-900">M0 — Foundation</dd>
                    </div>
                </dl>

                <p className="mt-10 text-sm text-slate-500">
                    Read <code className="rounded bg-slate-200 px-1.5 py-0.5">docs/ROADMAP.md</code> for what
                    comes next. Authentication and organizations arrive in M1.
                </p>
            </main>
        </>
    );
}
