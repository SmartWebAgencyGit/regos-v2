import '../css/app.css';
import '@fontsource/barlow-condensed/600.css';
import '@fontsource/barlow-condensed/700.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import AppLayout from '@/layouts/app-layout';

const pages = import.meta.glob<{ default: ResolvedComponent }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} · Player Portal` : 'Player Portal'),
    resolve: async (name) => (await pages[`./pages/${name}.tsx`]()).default,
    // One persistent layout: the header and navigation stay mounted between pages.
    layout: () => AppLayout,
    setup({ el, App, props }) {
        if (el) {
            createRoot(el).render(<App {...props} />);
        }
    },
    progress: { color: '#ffe500', delay: 150 },
});
