import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/maps/property-map.js',
                'resources/js/maps/browse-map.js',
            ],
            refresh: true,
        }),
    ],
});