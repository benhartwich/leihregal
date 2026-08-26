import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // zxing wird über resources/js/app.js eingebunden (setzt dort die
            // window-Globals). Der frühere eigene Eintrag war ein toter Entry.
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
