import type { HTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLElement>) {
    return (
        <section
            className={cn('rounded-2xl border border-white/[0.06] bg-ink-900/80 p-5 shadow-[inset_0_1px_0_0_rgb(255_255_255/0.04)] backdrop-blur-sm', className)}
            {...props}
        />
    );
}

export function Eyebrow({ className, children }: { className?: string; children: ReactNode }) {
    return <p className={cn('text-xs font-semibold uppercase tracking-[0.18em] text-ink-400', className)}>{children}</p>;
}

export function PageTitle({ eyebrow, title, children }: { eyebrow?: string; title: string; children?: ReactNode }) {
    return (
        <header className="px-1">
            {eyebrow && <Eyebrow className="text-brand">{eyebrow}</Eyebrow>}
            <h1 className="mt-1 font-display text-4xl font-bold uppercase leading-none tracking-wide">{title}</h1>
            {children && <div className="mt-2 text-ink-300">{children}</div>}
        </header>
    );
}
