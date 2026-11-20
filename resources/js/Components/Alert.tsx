import type { ReactNode } from 'react';

interface AlertProps {
    tone?: 'success' | 'error';
    children: ReactNode;
}

export default function Alert({ tone = 'success', children }: AlertProps) {
    const classes =
        tone === 'success'
            ? 'border-green-200 bg-green-50 text-green-800'
            : 'border-red-200 bg-red-50 text-red-800';

    return (
        <div role="status" className={`rounded-md border px-4 py-3 text-sm ${classes}`}>
            {children}
        </div>
    );
}
