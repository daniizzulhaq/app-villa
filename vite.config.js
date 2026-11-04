import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        // Aset di-build lokal lalu diunggah ke cPanel (public/build) — tidak perlu Node di server.
        chunkSizeWarningLimit: 900,
    },
});
