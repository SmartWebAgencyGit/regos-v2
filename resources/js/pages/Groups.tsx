import { Head, router } from '@inertiajs/react';
import { ChevronRight, Search, Users } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Alert } from '@/components/ui/alert';
import { Card, PageTitle } from '@/components/ui/card';
import { Avatar } from '@/components/ui/misc';
import { routes } from '@/lib/routes';

type Group = { bookingId: number; name: string; initials: string; players: number };

/**
 * On-site device: pick your group from today's bookings.
 */
export default function Groups({ groups }: { groups: Group[] | null }) {
    const [query, setQuery] = useState('');
    const [joining, setJoining] = useState<number | null>(null);

    const filtered = useMemo(() => (groups ?? []).filter((group) => group.name.toLowerCase().includes(query.trim().toLowerCase())), [groups, query]);

    const join = (bookingId: number) => {
        setJoining(bookingId);
        router.post(routes.joinGroup(bookingId), {}, { onFinish: () => setJoining(null) });
    };

    return (
        <>
            <Head title="Select your group" />
            <div className="animate-rise space-y-5">
                <PageTitle eyebrow="Welcome to base camp" title="Find your group">
                    Tap the name the booking was made under.
                </PageTitle>

                {groups === null && <Alert tone="error" title="Error loading groups">Please try again in a moment, or ask a member of staff.</Alert>}

                {groups !== null && groups.length === 0 && (
                    <Card className="py-10 text-center">
                        <Users className="mx-auto size-10 text-ink-400" aria-hidden />
                        <p className="mt-3 text-lg">There are no groups booked today</p>
                    </Card>
                )}

                {groups !== null && groups.length > 0 && (
                    <>
                        <label className="relative block">
                            <span className="sr-only">Search groups</span>
                            <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-400" aria-hidden />
                            <input
                                type="search"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                placeholder="Search by name"
                                className="h-14 w-full rounded-2xl border border-white/10 bg-ink-800 pr-4 pl-12 text-lg outline-none focus:border-brand focus:ring-4 focus:ring-brand/15"
                            />
                        </label>

                        <ul className="space-y-2">
                            {filtered.map((group) => (
                                <li key={group.bookingId}>
                                    <button
                                        type="button"
                                        onClick={() => join(group.bookingId)}
                                        disabled={joining !== null}
                                        className="flex w-full items-center gap-4 rounded-2xl border border-white/[0.06] bg-ink-900/80 p-4 text-left transition hover:border-brand/40 hover:bg-ink-850 disabled:opacity-60"
                                    >
                                        <Avatar initials={group.initials} className="size-12 text-xl" />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-lg font-semibold">{group.name}</span>
                                            <span className="text-sm text-ink-400">{group.players} players</span>
                                        </span>
                                        <span className="flex items-center gap-1 rounded-xl bg-brand px-4 py-2 text-sm font-bold text-ink-950">
                                            {joining === group.bookingId ? 'Joining…' : 'Join'}
                                            <ChevronRight className="size-4" aria-hidden />
                                        </span>
                                    </button>
                                </li>
                            ))}
                            {filtered.length === 0 && <li className="py-6 text-center text-ink-400">No groups match “{query}”.</li>}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}
