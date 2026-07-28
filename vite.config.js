import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const rootDir = dirname(fileURLToPath(import.meta.url));

function copyMapLibreWorkerAssets() {
    return {
        name: 'copy-maplibre-worker-assets',
        buildStart() {
            const targetDir = resolve(rootDir, 'public/vendor/maplibre-gl');
            mkdirSync(targetDir, { recursive: true });

            // maplibre-gl-worker.mjs imports its sibling maplibre-gl-shared.mjs via a
            // relative, unhashed specifier. Vite's `?url` import only copies the file
            // it's asked for, so the worker 404s on that import unless both files are
            // served together, unhashed, from a stable path outside Vite's asset pipeline.
            for (const file of ['maplibre-gl-worker.mjs', 'maplibre-gl-shared.mjs']) {
                copyFileSync(
                    resolve(rootDir, 'node_modules/maplibre-gl/dist', file),
                    resolve(targetDir, file),
                );
            }
        },
    };
}

export default defineConfig({
    plugins: [
        copyMapLibreWorkerAssets(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/map-diagnostic.js', 'resources/js/map-line-diagnostic.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
