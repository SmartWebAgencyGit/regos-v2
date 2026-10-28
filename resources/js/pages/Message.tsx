import { Head, router, usePage } from '@inertiajs/react';
import { CloudOff, Link2Off, RefreshCw } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { telHref } from '@/lib/utils';

type Props = {
    tone: 'denied' | 'unavailable';
    heading: string;
    message: string;
    note?: string;
};

export default function Message({ tone, heading, message, note }: Props) {
    const { brand } = usePage().props;
    const Icon = tone === 'unavailable' ? CloudOff : Link2Off;

    return (
        <>
            <Head title={heading} />
            <div className="flex min-h-[65dvh] animate-rise flex-col items-center justify-center px-2 text-center">
                <div className="relative mb-7">
                    <div className="absolute inset-0 rounded-3xl bg-brand/20 blur-2xl" />
                    <div className="relative grid size-20 place-items-center rounded-3xl bg-ink-850 ring-1 ring-white/10">
                        <Icon className="size-9 text-brand" aria-hidden />
                    </div>
                </div>
                <h1 className="font-display text-4xl font-bold uppercase leading-none tracking-wide">{heading}</h1>
                <p className="mt-4 max-w-sm text-lg text-ink-100">{message}</p>
                {note && <p className="mt-2 max-w-sm text-sm text-ink-400">{note}</p>}

                {tone === 'unavailable' ? (
                    <Button className="mt-8" onClick={() => router.reload()}>
                        <RefreshCw /> Try again
                    </Button>
                ) : (
                    <p className="mt-10 text-sm text-ink-400">
                        Need a hand? Call us on{' '}
                        <a href={telHref(brand.phone)} className="font-semibold text-brand">
                            {brand.phone}
                        </a>
                    </p>
                )}
            </div>
        </>
    );
}
