import { Head } from '@inertiajs/react';
import { ShieldCheck, UserPlus } from 'lucide-react';

import { ButtonLink } from '@/components/ui/button';
import { Card, PageTitle } from '@/components/ui/card';
import { Avatar, ProgressBar } from '@/components/ui/misc';
import { routes } from '@/lib/routes';
import type { Booking } from '@/types';

type Player = { id: number; name: string; initials: string; isYou: boolean };

export default function Players({ booking, players }: { booking: Booking; players: Player[] }) {
    const outstanding = Math.max(0, booking.paxBooked - players.length);

    return (
        <>
            <Head title="Squad" />
            <div className="animate-rise space-y-4">
                <PageTitle eyebrow={booking.party} title="Squad roster">
                    {players.length} of {booking.paxBooked} players registered
                </PageTitle>

                <ProgressBar value={players.length} max={booking.paxBooked} label="Players registered" />

                <Card className="p-2">
                    {players.length === 0 ? (
                        <p className="px-3 py-8 text-center text-ink-300">There are no registered players yet. Be the first!</p>
                    ) : (
                        <ul className="divide-y divide-white/[0.06]">
                            {players.map((player) => (
                                <li key={player.id} className="flex items-center gap-3 px-3 py-3">
                                    <Avatar initials={player.initials} highlight={player.isYou} />
                                    <span className="min-w-0 flex-1 truncate font-semibold">{player.name}</span>
                                    {player.isYou ? (
                                        <ButtonLink href={routes.boardingPass} size="sm">
                                            My pass
                                        </ButtonLink>
                                    ) : (
                                        <ShieldCheck className="size-5 text-success" aria-label="Registered" />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                {outstanding > 0 && (
                    <p className="px-1 text-sm text-ink-400">
                        {outstanding} {outstanding === 1 ? 'player still needs' : 'players still need'} to register.
                    </p>
                )}

                <ButtonLink href={routes.register} variant="secondary" size="lg" block>
                    <UserPlus /> Register a player
                </ButtonLink>
            </div>
        </>
    );
}
