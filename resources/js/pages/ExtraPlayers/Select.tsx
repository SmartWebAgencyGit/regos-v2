import { Head, useForm } from '@inertiajs/react';
import { ArrowRight, Info } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button, ButtonLink } from '@/components/ui/button';
import { Card, PageTitle } from '@/components/ui/card';
import { QuantityStepper } from '@/components/ui/misc';
import { FieldError } from '@/components/ui/text-field';
import { routes } from '@/lib/routes';
import { money } from '@/lib/utils';

type Package = { id: number; name: string; unitPrice: number; booked: number; max: number; quantity: number };

export default function Select({ currencySymbol, packages }: { currencySymbol: string; packages: Package[] }) {
    const form = useForm<{ quantities: Record<string, number> }>({
        quantities: Object.fromEntries(packages.map((pkg) => [String(pkg.id), pkg.quantity])),
    });

    const players = Object.values(form.data.quantities).reduce((sum, quantity) => sum + quantity, 0);
    const subtotal = packages.reduce((sum, pkg) => sum + (form.data.quantities[pkg.id] ?? 0) * pkg.unitPrice, 0);

    const setQuantity = (id: number, quantity: number) => {
        form.setData('quantities', { ...form.data.quantities, [id]: quantity });
        form.clearErrors();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(routes.extraPlayers, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Add players" />
            <form onSubmit={submit} className="animate-rise space-y-4">
                <PageTitle eyebrow="Build your army" title="Add players">
                    Choose how many players to add to your booking.
                </PageTitle>

                {packages.length === 0 ? (
                    <Card className="py-8 text-center text-ink-300">There are no player packages on this booking that can be added to online. Please give us a call to add players.</Card>
                ) : (
                    <>
                        {packages.map((pkg) => (
                            <Card key={pkg.id} className="flex flex-wrap items-center justify-between gap-4">
                                <div className="min-w-0">
                                    <p className="text-lg font-semibold">{pkg.name}</p>
                                    <p className="text-sm text-ink-400">
                                        {money(pkg.unitPrice, currencySymbol)} per player · {pkg.booked} booked
                                    </p>
                                </div>
                                <QuantityStepper label={pkg.name} value={form.data.quantities[pkg.id] ?? 0} max={pkg.max} onChange={(quantity) => setQuantity(pkg.id, quantity)} />
                            </Card>
                        ))}

                        <Card className="space-y-1">
                            <div className="flex items-baseline justify-between">
                                <span className="text-ink-300">
                                    {players} {players === 1 ? 'player' : 'players'}
                                </span>
                                <span className="font-display text-3xl font-bold tabular">{money(subtotal, currencySymbol)}</span>
                            </div>
                            <p className="flex items-center gap-1.5 text-xs text-ink-400">
                                <Info className="size-3.5" aria-hidden /> Any booking fee or tax is shown before you pay.
                            </p>
                            <FieldError message={form.errors.quantities} />
                        </Card>
                    </>
                )}

                <div className="flex gap-3">
                    <ButtonLink href={routes.boardingPass} variant="secondary" size="lg">
                        Back
                    </ButtonLink>
                    <Button type="submit" size="lg" block disabled={players === 0 || form.processing}>
                        Continue to payment <ArrowRight />
                    </Button>
                </div>
            </form>
        </>
    );
}
