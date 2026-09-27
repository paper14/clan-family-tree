// Self-hosted fonts (offline rule): serif for names and titles, sans for everything else.
import '@fontsource/source-serif-4/400.css';
import '@fontsource/source-serif-4/600.css';
import '@fontsource/source-serif-4/400-italic.css';
import '@fontsource/source-sans-3/400.css';
import '@fontsource/source-sans-3/600.css';
import '@fontsource/source-sans-3/400-italic.css';
// Design system: tokens and component styles (imported once, here).
import './design-system';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import AppLayout from './Layouts/AppLayout';

createInertiaApp({
    title: (title) => (title ? `${title} — Clan Family Tree` : 'Clan Family Tree'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (page.default.layout === undefined) {
            page.default.layout = (p) => <AppLayout>{p}</AppLayout>;
        }
        return page;
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: 'var(--lineage)', delay: 250 },
});
