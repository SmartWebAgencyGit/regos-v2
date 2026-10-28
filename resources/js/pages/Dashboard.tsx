import { Head } from '@inertiajs/react';
import { CalendarDays, ChevronRight, Clock, Flag, MapPin, Ticket, UserPlus } from 'lucide-react';
import type { ReactNode } from 'react';

import { Countdown } from '@/components/countdown';
import { Alert } from '@/components/ui/alert';
import { ButtonLink } from '@/components/ui/button';
import { Card, Eyebrow } from '@/components/ui/card';
import { ProgressBar } from '@/components/ui/misc';
import { routes } from '@/lib/routes';
import type { Booking, Registration } from '@/types';

type Props = {
    booking: Booking;
    registration: Registration | null;
};

export default function Dashboard({ booking, registration }: Props) {
    return (
        <>
            <Head title="Mission briefing" />
            <div className="animate-rise space-y-4">
                <section className="relative overflow-hidden rounded-3xl border border-white/[0.06] bg-gradient-to-br from-ink-800 via-ink-900 to-ink-950 p-6 shadow-2xl">
                    <div className="hazard-stripes absolute inset-x-0 top-0 h-1.5" aria-hidden />
                    <div className="absolute -top-16 -right-16 size-48 rounded-full bg-olive/40 blur-3xl" aria-hidden />
                    <Eyebrow className="text-brand">Mission briefing</Eyebrow>
                    <h1 className="mt-2 font-display text-5xl font-bold uppercase leading-[0.9] tracking-wide">{booking.site}</h1>
                    <p className="mt-1 flex items-center gap-1.5 text-ink-300">
                        <MapPin className="size-4" aria-hidden /> {booking.venue}
                    </p>

                    <dl className="mt-6 grid grid-cols-2 gap-3">
                        <Detail icon={<CalendarDays />} label={booking.gameDate.day}>
                            {booking.gameDate.date}
                        </Detail>
                        <Detail icon={<Clock />} label="Arrive by">
                            {booking.gameDate.time}
                        </Detail>
                        {booking.group && (
                            <Detail icon={<Flag />} label="Group" wide>
                                {booking.group}
                            </Detail>
                        )}
                    </dl>
                </section>

                {registration ? (
                    <ButtonLink href={routes.boardingPass} size="lg" block>
                        <Ticket /> {registration.complete ? 'View my boarding pass' : 'Finish my registration'}
                    </ButtonLink>
                ) : (
                    <Card className="border-brand/25 bg-brand/[0.05]">
                        <p className="font-display text-2xl font-bold uppercase">Ready to play?</p>
                        <p className="mt-1 text-ink-300">Every player needs to register before game day. It takes about two minutes, including the safety brief.</p>
                        <ButtonLink href={routes.register} size="lg" block className="mt-4">
                            <UserPlus /> Register to play
                        </ButtonLink>
                    </Card>
                )}

                <Card>
                    <Eyebrow>Countdown to game day</Eyebrow>
                    <div className="mt-3">
                        <Countdown iso={booking.gameDate.iso} />
                    </div>
                </Card>

                <Card>
                    <div className="flex items-end justify-between">
                        <div>
                            <Eyebrow>Squad</Eyebrow>
                            <p className="mt-1 font-display text-3xl font-bold">
                                {booking.registeredCount}
                                <span className="text-ink-400"> / {booking.paxBooked}</span>
                            </p>
                            <p className="text-sm text-ink-400">players registered</p>
                        </div>
                        <ButtonLink href={routes.players} variant="secondary" size="sm">
                            View squad <ChevronRight className="!size-4" />
                        </ButtonLink>
                    </div>
                    <div className="mt-4">
                        <ProgressBar value={booking.registeredCount} max={booking.paxBooked} label="Players registered" />
                    </div>
                </Card>

                {booking.registeredCount < booking.paxBooked && registration && (
                    <Alert tone="info">Share your booking link with the rest of your group so everyone can register before the day. It makes check-in much quicker.</Alert>
                )}
            </div>
        </>
    );
}

function Detail({ icon, label, children, wide = false }: { icon: ReactNode; label: string; children: ReactNode; wide?: boolean }) {
    return (
        <div className={wide ? 'col-span-2' : undefined}>
            <dt className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-ink-400 [&_svg]:size-3.5">
                {icon}
                {label}
            </dt>
            <dd className="mt-1 text-lg font-semibold text-ink-100">{children}</dd>
        </div>
    );
}
