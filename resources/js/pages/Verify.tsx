import { Head, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { FormEvent } from 'react';

import { VideoEmbed } from '@/components/video-embed';
import { Button } from '@/components/ui/button';
import { Card, PageTitle } from '@/components/ui/card';
import { ConsentToggle } from '@/components/ui/consent-toggle';
import { routes } from '@/lib/routes';
import type { ConsentLink, SafetyVideo } from '@/types';

type Props = {
    player: string;
    onSite: boolean;
    safetyVideo: SafetyVideo;
    consents: Record<'accept_terms' | 'safety_brief', { label: string; link: ConsentLink }>;
};

/**
 * For players registered by someone else who still need to accept the
 * indemnity statement and watch the safety brief.
 */
export default function Verify({ player, onSite, safetyVideo, consents }: Props) {
    const form = useForm({ accept_terms: false, safety_brief: onSite });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(routes.verify, { preserveScroll: true });
    };

    return (
        <>
            <Head title="One last step" />
            <form onSubmit={submit} className="animate-rise space-y-4">
                <PageTitle eyebrow={`Hi ${player}`} title="One last step">
                    Before we can issue your boarding pass, please confirm the following.
                </PageTitle>

                <Card className="space-y-4">
                    <ConsentToggle
                        name="accept_terms"
                        label={consents.accept_terms.label}
                        link={consents.accept_terms.link}
                        checked={form.data.accept_terms}
                        onChange={(checked) => form.setData('accept_terms', checked)}
                        error={form.errors.accept_terms}
                    />

                    {!onSite && (
                        <>
                            <VideoEmbed video={safetyVideo} />
                            <ConsentToggle
                                name="safety_brief"
                                label={consents.safety_brief.label}
                                link={consents.safety_brief.link}
                                checked={form.data.safety_brief}
                                onChange={(checked) => form.setData('safety_brief', checked)}
                                error={form.errors.safety_brief}
                            />
                        </>
                    )}
                </Card>

                <Button type="submit" size="lg" block disabled={form.processing}>
                    <Check /> {form.processing ? 'Confirming…' : 'Confirm'}
                </Button>
            </form>
        </>
    );
}
