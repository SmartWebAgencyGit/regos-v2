import { CircleAlert } from 'lucide-react';
import type { InputHTMLAttributes } from 'react';

import { cn } from '@/lib/utils';

type Props = InputHTMLAttributes<HTMLInputElement> & {
    name: string;
    label: string;
    error?: string;
    hint?: string;
};

export function TextField({ name, label, error, hint, className, ...props }: Props) {
    const describedBy = error ? `${name}-error` : hint ? `${name}-hint` : undefined;

    return (
        <div className={className}>
            <label htmlFor={name} className="mb-1.5 block text-sm font-medium text-ink-300">
                {label}
            </label>
            <input
                id={name}
                name={name}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                className={cn(
                    'block h-12 w-full rounded-xl border bg-ink-800 px-4 text-base text-ink-100 outline-none transition placeholder:text-ink-400',
                    'focus:border-brand focus:ring-4 focus:ring-brand/15 read-only:cursor-default read-only:opacity-70',
                    error ? 'border-danger/70' : 'border-white/10',
                )}
                {...props}
            />
            <FieldError id={`${name}-error`} message={error} />
            {!error && hint && (
                <p id={`${name}-hint`} className="mt-1.5 text-xs leading-relaxed text-ink-400">
                    {hint}
                </p>
            )}
        </div>
    );
}

export function FieldError({ id, message }: { id?: string; message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} role="alert" className="mt-1.5 flex items-start gap-1.5 text-sm text-danger">
            <CircleAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
            {message}
        </p>
    );
}
