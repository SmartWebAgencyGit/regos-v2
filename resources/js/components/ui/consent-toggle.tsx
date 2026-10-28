import { Check } from 'lucide-react';

import { FieldError } from '@/components/ui/text-field';
import { cn } from '@/lib/utils';
import type { ConsentLink } from '@/types';

type Props = {
    name: string;
    label: string;
    link?: ConsentLink;
    checked: boolean;
    onChange: (checked: boolean) => void;
    error?: string;
    optional?: boolean;
};

/**
 * A declaration the player switches on. ":link" in the label becomes a link
 * (opened in a new tab so the form is never lost).
 */
export function ConsentToggle({ name, label, link, checked, onChange, error, optional = false }: Props) {
    return (
        <div>
            <label
                className={cn(
                    'flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition',
                    checked
                        ? 'border-brand/40 bg-brand/[0.06]'
                        : error
                          ? 'border-danger/60 bg-danger/[0.04]'
                          : 'border-white/[0.06] bg-ink-850 hover:border-white/15',
                )}
            >
                <span className="relative mt-0.5 inline-flex h-7 w-12 shrink-0">
                    <input
                        type="checkbox"
                        role="switch"
                        name={name}
                        checked={checked}
                        onChange={(event) => onChange(event.target.checked)}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={error ? `${name}-error` : undefined}
                        className="peer absolute inset-0 z-10 cursor-pointer opacity-0"
                    />
                    <span className="absolute inset-0 rounded-full bg-ink-600 transition peer-checked:bg-brand peer-focus-visible:ring-4 peer-focus-visible:ring-brand/30" />
                    <span className="absolute top-1 left-1 grid size-5 place-items-center rounded-full bg-white text-ink-950 shadow transition peer-checked:translate-x-5">
                        {checked && <Check className="size-3.5" strokeWidth={3} aria-hidden />}
                    </span>
                </span>
                <span className={cn('text-[15px] leading-relaxed', checked ? 'text-ink-100' : 'text-ink-300')}>
                    <ConsentText label={label} link={link} />
                    {optional && <span className="ml-1 text-ink-400">(optional)</span>}
                </span>
            </label>
            <FieldError id={`${name}-error`} message={error} />
        </div>
    );
}

export function ConsentText({ label, link }: { label: string; link?: ConsentLink }) {
    const [before, after] = label.split(':link');

    if (after === undefined || !link) {
        return <>{before}</>;
    }

    return (
        <>
            {before}
            {link.href ? (
                <a
                    href={link.href}
                    target="_blank"
                    rel="noopener"
                    onClick={(event) => event.stopPropagation()}
                    className="font-semibold text-brand underline decoration-brand/40 underline-offset-4 hover:decoration-brand"
                >
                    {link.text}
                </a>
            ) : (
                <strong className="font-semibold text-ink-100">{link.text}</strong>
            )}
            {after}
        </>
    );
}
