import { Link, usePage } from '@inertiajs/react';
import { House, ScrollText, Ticket, UserPlus, Users } from 'lucide-react';

import { routes } from '@/lib/routes';
import { cn } from '@/lib/utils';

/**
 * App-style tab bar for signed-in visitors.
 */
export function BottomNav() {
    const { url, props } = usePage();
    const portal = props.portal;

    if (!portal) {
        return null;
    }

    const tabs = [
        { href: routes.dashboard, label: 'Home', icon: House, match: [routes.dashboard] },
        { href: routes.players, label: 'Squad', icon: Users, match: [routes.players] },
        portal.hasRegistration
            ? { href: routes.boardingPass, label: 'Pass', icon: Ticket, match: [routes.boardingPass, routes.verify, routes.extraPlayers, routes.thanks] }
            : { href: routes.register, label: 'Register', icon: UserPlus, match: [routes.register] },
        { href: routes.rules, label: 'Rules', icon: ScrollText, match: [routes.rules] },
    ];

    const path = url.split('?')[0];

    return (
        <nav aria-label="Portal" className="fixed inset-x-0 bottom-0 z-30 border-t border-white/[0.06] bg-ink-950/85 backdrop-blur-xl safe-bottom">
            <ul className="mx-auto grid h-16 max-w-2xl grid-cols-4">
                {tabs.map(({ href, label, icon: Icon, match }) => {
                    const active = match.some((prefix) => path === prefix || path.startsWith(prefix + '/'));

                    return (
                        <li key={label}>
                            <Link
                                href={href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                onClick={(event) => {
                                    // Re-opening the current page would reset a half-filled form.
                                    if (path === href) {
                                        event.preventDefault();
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    }
                                }}
                                className={cn('relative flex h-full flex-col items-center justify-center gap-1 text-[11px] font-semibold uppercase tracking-wider transition', active ? 'text-brand' : 'text-ink-400 hover:text-ink-100')}
                            >
                                {active && <span className="absolute top-0 h-0.5 w-10 rounded-full bg-brand" />}
                                <Icon className="size-[22px]" strokeWidth={active ? 2.4 : 2} aria-hidden />
                                {label}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
