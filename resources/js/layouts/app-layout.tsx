import { Link, usePage } from '@inertiajs/react';
import { FlaskConical, LogOut, Phone } from 'lucide-react';
import type { ReactNode } from 'react';

import { BottomNav } from '@/components/bottom-nav';
import { FlashToasts } from '@/components/flash-toasts';
import { routes } from '@/lib/routes';
import { cn, telHref } from '@/lib/utils';

/**
 * Persistent app shell: stays mounted while pages change underneath it.
 */
export default function AppLayout({ children }: { children: ReactNode }) {
    const { brand, devMode, onSite, portal } = usePage().props;
    const iconButton = 'grid size-11 place-items-center rounded-full text-ink-100 ring-1 ring-inset ring-white/10 transition hover:bg-white/5 hover:text-brand';

    return (
        <div className={cn('min-h-dvh', portal ? 'pb-24' : 'pb-10')}>
            {(devMode.demoBookings || devMode.simulatedPayments) && (
                <div className="flex items-center justify-center gap-2 bg-fuchsia-700/90 px-4 py-1.5 text-center text-xs font-medium text-white" role="note">
                    <FlaskConical className="size-3.5 shrink-0" aria-hidden />
                    Development mode:{' '}
                    {[devMode.demoBookings && 'demo bookings only', devMode.simulatedPayments && 'card payments are simulated'].filter(Boolean).join(' · ')}
                </div>
            )}

            <header className="sticky top-0 z-30 border-b border-white/[0.06] bg-ink-950/80 pt-[env(safe-area-inset-top)] backdrop-blur-xl">
                <div className="mx-auto flex h-16 max-w-2xl items-center gap-3 px-4">
                    <Link href={portal ? routes.dashboard : routes.entry} className="shrink-0" aria-label="Home">
                        <img src="/images/logo.png" alt={brand.name} className="h-10 w-auto" />
                    </Link>
                    <div className="min-w-0 flex-1 leading-tight">
                        <p className="text-[10px] font-bold uppercase tracking-[0.24em] text-brand">Player Portal</p>
                        <p className="truncate font-display text-xl font-semibold uppercase tracking-wide">{portal?.party ?? brand.name}</p>
                    </div>
                    <a href={telHref(brand.phone)} className={iconButton} aria-label={`Call us on ${brand.phone}`}>
                        <Phone className="size-5" aria-hidden />
                    </a>
                    {onSite && portal && (
                        <Link href={routes.logout} method="post" as="button" className={iconButton} aria-label="Leave this group">
                            <LogOut className="size-5" aria-hidden />
                        </Link>
                    )}
                </div>
            </header>

            <FlashToasts />

            <main className="mx-auto max-w-2xl px-4 pt-5">{children}</main>

            <BottomNav />
        </div>
    );
}
