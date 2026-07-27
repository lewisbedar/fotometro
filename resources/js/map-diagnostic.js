import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const container = document.getElementById('metro-map-diagnostic');

const cartoStyle = 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json';
const rasterStyle = {
    version: 8,
    sources: {
        osm: {
            type: 'raster',
            tiles: [
                'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            ],
            tileSize: 256,
            attribution: '© OpenStreetMap contributors',
            maxzoom: 19,
        },
    },
    layers: [
        {
            id: 'osm',
            type: 'raster',
            source: 'osm',
        },
    ],
};

const params = new URLSearchParams(window.location.search);
const diagnosticMode = params.get('style') === 'vector' ? 'vector' : 'raster';
const diagnosticStyle = diagnosticMode === 'vector' ? cartoStyle : rasterStyle;

try {
    if (! container) {
        throw new Error('Diagnostic container #metro-map-diagnostic was not found.');
    }

    const map = new maplibregl.Map({
        container,
        style: diagnosticStyle,
        center: [2.3522, 48.8566],
        zoom: 11.5,
        minZoom: 0,
        maxZoom: 19,
    });

    map.addControl(new maplibregl.NavigationControl());

    console.debug('[diagnostic] mode', {
        style: diagnosticMode,
        retainedConfiguration: 'raster',
        vectorStatus: 'experimental',
    });

    map.on('style.load', () => {
        const style = map.getStyle();

        console.debug('[diagnostic] style.load', {
            name: style?.name ?? null,
            sources: Object.keys(style?.sources ?? {}),
            layerCount: style?.layers?.length ?? 0,
            raw: style,
        });
    });

    map.on('load', () => {
        console.debug('[diagnostic] load', {
            center: map.getCenter(),
            zoom: map.getZoom(),
            loaded: map.loaded(),
            styleLoaded: map.isStyleLoaded(),
        });

        map.resize();
    });

    map.on('idle', () => {
        console.debug('[diagnostic] idle', {
            loaded: map.loaded(),
            styleLoaded: map.isStyleLoaded(),
        });
    });

    map.on('error', (event) => {
        console.error('[diagnostic] raw error event', event);
    });

    setTimeout(() => {
        console.debug('[diagnostic] state after 5s', {
            loaded: map.loaded(),
            styleLoaded: map.isStyleLoaded(),
            style: map.getStyle()?.name ?? null,
            sourceIds: Object.keys(map.getStyle()?.sources ?? {}),
            layerCount: map.getStyle()?.layers?.length ?? 0,
        });
    }, 5000);
} catch (error) {
    console.error('[diagnostic] constructor failure', error);

    if (container) {
        container.textContent = error instanceof Error
            ? `${error.name}: ${error.message}`
            : String(error);
    }
}
