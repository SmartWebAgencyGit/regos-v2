import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Tone = 'info' | 'success' | 'warning' | 'error';

const tones: Record<Tone, { box: string; icon: typeof Info }> = {
    info: { box: 'border-sky-400/25 bg-sky-400/[0.07] text-sky-100', icon: Info },
    success: { box: 'border-success/30 bg-success/[0.08] text-green-100', icon: CircleCheck },
    warning: { box: 'border-warning/35 bg-warning/[0.08] text-amber-100', icon: TriangleAlert },
    error: { box: 'border-danger/40 bg-danger/[0.09] text-red-100', icon: CircleAlert },
};

export function Alert({ tone = 'info', title, children, className }: { tone?: Tone; title?: string; children?: ReactNode; className?: string }) {
    const { box, icon: Icon } = tones[tone];

    return (
        <div role={tone === 'error' ? 'alert' : 'status'} className={cn('flex gap-3 rounded-2xl border p-4', box, className)}>
            <Icon className="mt-0.5 size-5 shrink-0" aria-hidden />
            <div className="min-w-0 text-[15px] leading-relaxed">
                {title && <p className="font-semibold text-white">{title}</p>}
                {children && <div className={cn(title && 'mt-0.5', 'opacity-90')}>{children}</div>}
            </div>
        </div>
    );
}
