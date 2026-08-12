import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { readFileSync } from 'node:fs';

function maplibreWorkerDependencies() {
    return {
        name: 'maplibre-worker-dependencies',
        generateBundle() {
            this.emitFile({
                type: 'asset',
                fileName: 'assets/maplibre-gl-shared.mjs',
                source: readFileSync(new URL('./node_modules/maplibre-gl/dist/maplibre-gl-shared.mjs', import.meta.url)),
            });
        },
    };
}

export default defineConfig({
    plugins: [
        maplibreWorkerDependencies(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/maps/districts-map.js',
                'resources/js/maps/districts-widget-map.js',
                'resources/js/maps/traffic-map.js',
                'resources/js/maps/traffic-portal.js',
                'resources/js/maps/infrastructure-map.js',
                'resources/js/maps/climate-map.js',
                'resources/js/maps/energy-maps.js',
                'resources/js/maps/energy-infrastructure-map.js',
                'resources/js/maps/internet-maps.js',
                'resources/js/maps/internet-infrastructure-map.js',
            ],
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
