import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { Alert } from '@/components/ui/alert';
import type { Flash } from '@/types';

const tones = { success: 'success', warning: 'warning', error: 'error' } as const;

/**
 * Shows the session flash messages from the latest response. Success
 * messages fade after a few seconds; warnings and errors stay until closed.
 */
export function FlashToasts() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState<Flash>(flash);

    useEffect(() => {
        setVisible(flash);

        if (flash.success && !flash.warning && !flash.error) {
            const timer = window.setTimeout(() => setVisible({}), 5000);

            return () => window.clearTimeout(timer);
        }
    }, [flash]);

    const messages = (Object.keys(tones) as Array<keyof typeof tones>).filter((tone) => visible[tone]);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="mx-auto max-w-2xl space-y-2 px-4 pt-4">
            {messages.map((tone) => (
                <div key={tone} className="relative animate-rise">
                    <Alert tone={tones[tone]} className="pr-12 shadow-lg">
                        {visible[tone]}
                    </Alert>
                    <button
                        type="button"
                        onClick={() => setVisible((current) => ({ ...current, [tone]: undefined }))}
                        className="absolute top-3 right-3 rounded-lg p-1 text-white/70 hover:bg-white/10 hover:text-white"
                        aria-label="Dismiss"
                    >
                        <X className="size-4" />
                    </button>
                </div>
            ))}
        </div>
    );
}
