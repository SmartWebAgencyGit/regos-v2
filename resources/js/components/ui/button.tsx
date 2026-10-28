import { Link } from '@inertiajs/react';
import type { AnchorHTMLAttributes, ButtonHTMLAttributes, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Variant = 'primary' | 'secondary' | 'ghost';
type Size = 'sm' | 'md' | 'lg';

type StyleProps = {
    variant?: Variant;
    size?: Size;
    block?: boolean;
    className?: string;
};

const variants: Record<Variant, string> = {
    primary: 'bg-brand text-ink-950 shadow-[0_10px_30px_-12px_rgb(255_229_0/0.55)] hover:bg-brand-300',
    secondary: 'bg-ink-800 text-ink-100 ring-1 ring-inset ring-white/10 hover:bg-ink-700',
    ghost: 'text-ink-300 hover:bg-white/5 hover:text-ink-100',
};

const sizes: Record<Size, string> = {
    sm: 'h-10 px-4 text-sm',
    md: 'h-12 px-5 text-base',
    lg: 'h-14 px-6 text-lg',
};

export function buttonClasses({ variant = 'primary', size = 'md', block = false, className }: StyleProps): string {
    return cn(
        'inline-flex select-none items-center justify-center gap-2 rounded-xl font-semibold transition duration-150 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-5 [&_svg]:shrink-0',
        variants[variant],
        sizes[size],
        block && 'w-full',
        className,
    );
}

export function Button({ variant, size, block, className, type = 'button', ...props }: StyleProps & ButtonHTMLAttributes<HTMLButtonElement>) {
    return <button type={type} className={buttonClasses({ variant, size, block, className })} {...props} />;
}

type ButtonLinkProps = StyleProps & {
    href: string;
    children: ReactNode;
    /** Opens outside the portal, in a new tab. */
    external?: boolean;
} & Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'>;

export function ButtonLink({ href, external = false, variant, size, block, className, children, ...props }: ButtonLinkProps) {
    const classes = buttonClasses({ variant, size, block, className });

    if (external) {
        return (
            <a href={href} target="_blank" rel="noopener" className={classes} {...props}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} prefetch className={classes}>
            {children}
        </Link>
    );
}
