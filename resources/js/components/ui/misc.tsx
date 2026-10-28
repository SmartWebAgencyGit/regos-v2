import { Minus, Plus } from 'lucide-react';

import { cn } from '@/lib/utils';

const avatarTones = ['bg-olive text-olive-300', 'bg-brand/15 text-brand', 'bg-sky-500/15 text-sky-300', 'bg-rose-500/15 text-rose-300', 'bg-emerald-500/15 text-emerald-300'];

export function Avatar({ initials, highlight = false, className }: { initials: string; highlight?: boolean; className?: string }) {
    const tone = highlight ? 'bg-brand text-ink-950' : avatarTones[(initials.charCodeAt(0) + (initials.charCodeAt(1) || 0)) % avatarTones.length];

    return (
        <span aria-hidden className={cn('grid size-11 shrink-0 place-items-center rounded-full font-display text-lg font-bold', tone, className)}>
            {initials}
        </span>
    );
}

export function ProgressBar({ value, max, label }: { value: number; max: number; label: string }) {
    const percent = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0;

    return (
        <div role="progressbar" aria-label={label} aria-valuemin={0} aria-valuemax={max} aria-valuenow={value} className="h-2.5 overflow-hidden rounded-full bg-ink-700">
            <div className="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand transition-[width] duration-700" style={{ width: `${percent}%` }} />
        </div>
    );
}

export function QuantityStepper({ value, max, onChange, label }: { value: number; max: number; onChange: (value: number) => void; label: string }) {
    const button = 'grid size-11 place-items-center rounded-xl bg-ink-800 text-ink-100 ring-1 ring-inset ring-white/10 transition hover:bg-ink-700 active:scale-95 disabled:opacity-40';

    return (
        <div className="flex items-center gap-2" role="group" aria-label={label}>
            <button type="button" className={button} onClick={() => onChange(Math.max(0, value - 1))} disabled={value <= 0} aria-label={`One fewer: ${label}`}>
                <Minus className="size-5" />
            </button>
            <output aria-live="polite" className="w-10 text-center font-display text-3xl font-bold tabular">
                {value}
            </output>
            <button
                type="button"
                className={cn(button, 'bg-brand text-ink-950 ring-0 hover:bg-brand-300')}
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={value >= max}
                aria-label={`One more: ${label}`}
            >
                <Plus className="size-5" />
            </button>
        </div>
    );
}
