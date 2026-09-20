import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

// The statamic plugin externalizes Vue to the copy the control panel already
// loaded. Bundling a second one would give the page a different reactivity
// system to the CP around it.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/cp.js'],
            publicDirectory: 'public',
        }),
        statamic(),
    ],
});
