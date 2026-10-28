import { Play } from 'lucide-react';
import { useState } from 'react';

import type { SafetyVideo } from '@/types';

/**
 * YouTube safety brief that only loads the (heavy) player once tapped,
 * which keeps the page quick on weak mobile signal at the venues.
 */
export function VideoEmbed({ video, title = 'Paintball Safety Brief' }: { video: SafetyVideo; title?: string }) {
    const [playing, setPlaying] = useState(false);
    const id = video.embed.match(/embed\/([^?]+)/)?.[1];

    return (
        <div className="relative aspect-video w-full overflow-hidden rounded-2xl bg-black ring-1 ring-white/10">
            {playing || !id ? (
                <iframe
                    src={`${video.embed}${video.embed.includes('?') ? '&' : '?'}autoplay=1`}
                    title={title}
                    className="absolute inset-0 size-full"
                    allow="autoplay; encrypted-media; picture-in-picture"
                    allowFullScreen
                />
            ) : (
                <button type="button" onClick={() => setPlaying(true)} className="group absolute inset-0 size-full" aria-label={`Play the ${title}`}>
                    <img src={`https://i.ytimg.com/vi/${id}/hqdefault.jpg`} alt="" className="size-full object-cover opacity-70 transition group-hover:opacity-90" loading="lazy" />
                    <span className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                    <span className="absolute top-1/2 left-1/2 grid size-16 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-brand text-ink-950 shadow-xl transition group-hover:scale-105">
                        <Play className="ml-1 size-7" fill="currentColor" />
                    </span>
                    <span className="absolute bottom-3 left-4 text-left text-sm font-semibold text-white">{title}</span>
                </button>
            )}
        </div>
    );
}
