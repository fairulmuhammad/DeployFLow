import type { ComponentProps } from 'react';

export default function PrimaryButton({ className = '', disabled, children, ...props }: ComponentProps<'button'>) {
    return (
        <button
            {...props}
            disabled={disabled}
            className={`inline-flex items-center justify-center rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${className}`}
        >
            {children}
        </button>
    );
}
