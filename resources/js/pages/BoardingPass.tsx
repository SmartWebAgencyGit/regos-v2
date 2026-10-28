import { Head, usePage } from '@inertiajs/react';
import { ArrowUpRight, FileText, ListChecks, Map, MessageSquareText, Navigation, UserPlus } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';

import { Alert } from '@/components/ui/alert';
import { ButtonLink } from '@/components/ui/button';
import { Card, Eyebrow } from '@/components/ui/card';
import { routes } from '@/lib/routes';
import type { Booking, Registration } from '@/types';

type Props = {
    booking: Booking;
    registration: Registration;
    qrUrl: string | null;
    links: { directions: string | null; map: string | null; infoPack: string | null; itinerary: string | null };
    canAddPlayers: boolean;
};

export default function BoardingPass({ booking, registration, qrUrl, links, canAddPlayers }: Props) {
    const { brand } = usePage().props;

    const resources = [
        { href: links.directions, label: 'Directions', hint: 'Open in Maps', icon: Navigation },
        { href: links.map, label: 'Site map', hint: 'PDF', icon: Map },
        { href: links.infoPack, label: 'Info pack', hint: 'What to bring', icon: FileText },
        { href: links.itinerary, label: 'Day itinerary', hint: 'How the day runs', icon: ListChecks },
    ].filter((resource): resource is typeof resource & { href: string } => resource.href !== null);

    return (
        <>
            <Head title="Boarding pass" />
            <div className="animate-rise space-y-4">
                {!registration.confirmed && (
                    <Alert tone="warning" title="Check your phone to finish registering">
                        <p>We’ve sent a verification link by SMS and email. Tap it to activate your boarding pass. If it hasn’t arrived, check your junk folder.</p>
                        <ButtonLink href={routes.editRegistration} variant="secondary" size="sm" className="mt-3">
                            <MessageSquareText className="!size-4" /> Check my contact details
                        </ButtonLink>
                    </Alert>
                )}

                <article className="overflow-hidden rounded-3xl bg-ink-100 text-ink-950 shadow-[0_30px_80px_-30px_rgb(255_229_0/0.35)]" aria-label="Boarding pass">
                    <div className="flex items-center justify-between gap-4 bg-brand px-6 py-4">
                        <div className="min-w-0">
                            <p className="text-[11px] font-bold uppercase tracking-[0.24em]">Boarding pass</p>
                            <p className="truncate font-display text-3xl font-bold uppercase leading-none">{booking.site}</p>
                            <p className="mt-0.5 text-xs font-semibold">{brand.name}</p>
                        </div>
                        <span className="shrink-0 rounded-xl bg-ink-950 px-2 py-1.5">
                            <img src="/images/logo.png" alt={brand.name} className="h-9 w-auto" />
                        </span>
                    </div>

                    <div className="flex flex-col items-center px-6 py-6">
                        {qrUrl ? (
                            <>
                                <img src={qrUrl} alt="Check-in QR code" className="size-56 rounded-xl bg-white p-2 ring-1 ring-ink-300" />
                                <p className="mt-3 text-sm font-semibold text-ink-600">Show this to staff at check-in</p>
                            </>
                        ) : (
                            <div className="grid size-56 place-items-center rounded-xl border-2 border-dashed border-ink-300 p-6 text-center text-sm font-medium text-ink-600">
                                Your check-in code appears here once you’ve tapped the link we sent you.
                            </div>
                        )}
                    </div>

                    <div className="relative flex items-center" aria-hidden>
                        <span className="absolute -left-3 size-6 rounded-full bg-ink-950" />
                        <span className="mx-5 w-full border-t-2 border-dashed border-ink-300" />
                        <span className="absolute -right-3 size-6 rounded-full bg-ink-950" />
                    </div>

                    <dl className="grid grid-cols-2 gap-x-4 gap-y-4 px-6 py-5">
                        <TicketField label="Player">{registration.name}</TicketField>
                        <TicketField label={booking.group ? 'Group' : 'Organiser'}>{booking.group ?? booking.organiser}</TicketField>
                        <TicketField label="Date">{booking.gameDate.short}</TicketField>
                        <TicketField label="Arrive by">{booking.gameDate.time}</TicketField>
                    </dl>
                </article>

                {resources.length > 0 && (
                    <Card>
                        <Eyebrow>Get ready for the day</Eyebrow>
                        <div className="mt-3 grid grid-cols-2 gap-2">
                            {resources.map(({ href, label, hint, icon }) => (
                                <ResourceTile key={label} href={href} label={label} hint={hint} icon={icon} />
                            ))}
                        </div>
                    </Card>
                )}

                {canAddPlayers && (
                    <Card className="flex items-center gap-4">
                        <div className="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand/10 text-brand">
                            <UserPlus className="size-6" aria-hidden />
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold">Bring more players?</p>
                            <p className="text-sm text-ink-400">Add players to your booking online.</p>
                        </div>
                        <ButtonLink href={routes.extraPlayers} size="sm">
                            Add
                        </ButtonLink>
                    </Card>
                )}
            </div>
        </>
    );
}

function TicketField({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="text-[11px] font-bold uppercase tracking-[0.16em] text-ink-400">{label}</dt>
            <dd className="mt-0.5 truncate font-display text-xl font-bold uppercase leading-tight">{children}</dd>
        </div>
    );
}

function ResourceTile({ href, label, hint, icon: Icon }: { href: string; label: string; hint: string; icon: ComponentType<{ className?: string }> }) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener"
            className="group relative flex flex-col gap-3 rounded-2xl bg-ink-800 p-4 ring-1 ring-inset ring-white/5 transition hover:bg-ink-700 hover:ring-brand/30"
        >
            <Icon className="size-6 text-brand" />
            <span>
                <span className="block font-semibold">{label}</span>
                <span className="text-xs text-ink-400">{hint}</span>
            </span>
            <ArrowUpRight className="absolute top-3 right-3 size-4 text-ink-400 transition group-hover:text-brand" />
        </a>
    );
}
