import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '127.0.0.1', // Forces IPv4 instead of IPv6 [::1]
        cors: true,        // Allows your custom domain to request assets
        hmr: {
            host: '127.0.0.1',
        },
    },
});
