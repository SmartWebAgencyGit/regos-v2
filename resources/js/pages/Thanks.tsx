import { Head } from '@inertiajs/react';
import { CircleCheck, Ticket } from 'lucide-react';

import { ButtonLink } from '@/components/ui/button';
import { routes } from '@/lib/routes';

export default function Thanks() {
    return (
        <>
            <Head title="Payment received" />
            <div className="flex min-h-[60dvh] animate-rise flex-col items-center justify-center text-center">
                <div className="relative mb-7">
                    <div className="absolute inset-0 rounded-full bg-success/25 blur-2xl" />
                    <CircleCheck className="relative size-20 text-success" strokeWidth={1.5} aria-hidden />
                </div>
                <h1 className="font-display text-4xl font-bold uppercase tracking-wide">Payment received</h1>
                <p className="mt-4 max-w-sm text-lg text-ink-300">
                    Thank you! One of our event co-ordinators will update your booking and send over the booking confirmation shortly.
                </p>
                <ButtonLink href={routes.boardingPass} size="lg" className="mt-8">
                    <Ticket /> Back to my boarding pass
                </ButtonLink>
            </div>
        </>
    );
}
