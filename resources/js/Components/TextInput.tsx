import type { ComponentProps } from 'react';

export default function TextInput({ className = '', ...props }: ComponentProps<'input'>) {
    return (
        <input
            {...props}
            className={`block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none placeholder:text-slate-400 focus:border-slate-900 focus:ring-1 focus:ring-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 ${className}`}
        />
    );
}
