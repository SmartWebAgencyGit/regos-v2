import { useEffect, useState } from 'react';

function remaining(target: number) {
    const seconds = Math.max(0, Math.floor((target - Date.now()) / 1000));

    return {
        done: seconds === 0,
        units: [
            { label: 'Days', value: Math.floor(seconds / 86400) },
            { label: 'Hours', value: Math.floor((seconds % 86400) / 3600) },
            { label: 'Mins', value: Math.floor((seconds % 3600) / 60) },
            { label: 'Secs', value: seconds % 60 },
        ],
    };
}

/**
 * Live countdown to the game start (an ISO 8601 timestamp with offset).
 */
export function Countdown({ iso }: { iso: string }) {
    const target = new Date(iso).getTime();
    const [state, setState] = useState(() => remaining(target));

    useEffect(() => {
        const timer = window.setInterval(() => setState(remaining(target)), 1000);

        return () => window.clearInterval(timer);
    }, [target]);

    if (state.done) {
        return <p className="py-3 text-center font-display text-3xl font-bold uppercase text-brand">It’s game day!</p>;
    }

    return (
        <div className="grid grid-cols-4 gap-2" role="timer" aria-label="Time until game day">
            {state.units.map(({ label, value }) => (
                <div key={label} className="rounded-xl bg-ink-800 px-1 py-3 text-center ring-1 ring-inset ring-white/5">
                    <div className="font-display text-4xl font-bold leading-none tabular text-ink-100">{String(value).padStart(2, '0')}</div>
                    <div className="mt-1.5 text-[11px] font-semibold uppercase tracking-[0.16em] text-ink-400">{label}</div>
                </div>
            ))}
        </div>
    );
}
