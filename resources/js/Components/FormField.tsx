import type { ReactNode } from 'react';

interface FormFieldProps {
    label: string;
    htmlFor: string;
    error?: string;
    help?: string;
    children: ReactNode;
}

export default function FormField({ label, htmlFor, error, help, children }: FormFieldProps) {
    return (
        <div>
            <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                {label}
            </label>

            <div className="mt-1">{children}</div>

            {help && !error && <p className="mt-1 text-sm text-slate-500">{help}</p>}

            {error && (
                <p id={`${htmlFor}-error`} role="alert" className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
