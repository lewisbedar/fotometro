import 'maplibre-gl/dist/maplibre-gl.css';
import * as maplibregl from 'maplibre-gl';

const maplibreWorkerUrl = '/vendor/maplibre-gl/maplibre-gl-worker.mjs';

const params = new URLSearchParams(window.location.search);
const diagnosticErrors = [];

if (! maplibregl.getWorkerUrl?.()) {
    maplibregl.setWorkerUrl?.(maplibreWorkerUrl);
}

window.fotometroMapLibreWorkerUrl = maplibregl.getWorkerUrl?.() ?? maplibreWorkerUrl;

function number(value, fallback) {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : fallback;
}

function recordError(label, payload) {
    diagnosticErrors.push({ label, payload });
    console.error(label, payload);
}

window.addEventListener('error', (event) => {
    recordError('[fotometro diagnostic] WINDOW ERROR', {
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error,
        stack: event.error?.stack,
    });
});

window.addEventListener('unhandledrejection', (event) => {
    recordError('[fotometro diagnostic] UNHANDLED REJECTION', {
        reason: event.reason,
        message: event.reason?.message,
        stack: event.reason?.stack,
    });
});

function buildBaseStyle(config) {
    if (params.get('basemap') === 'none') {
        return {
            version: 8,
            sources: {},
            layers: [{
                id: 'background',
                type: 'background',
                paint: { 'background-color': '#eeeeee' },
            }],
        };
    }

    return {
        version: 8,
        sources: {
            'fotometro-basemap': {
                type: 'raster',
                tiles: [config.rasterUrl],
                tileSize: number(config.rasterTileSize, 256),
                minzoom: 0,
                maxzoom: number(config.maxZoom, 19),
                attribution: config.attribution || '',
            },
        },
        layers: [{
            id: 'fotometro-basemap',
            type: 'raster',
            source: 'fotometro-basemap',
            minzoom: 0,
            maxzoom: number(config.maxZoom, 19),
        }],
    };
}

function normalizePosition(position) {
    if (! Array.isArray(position) || position.length !== 2) {
        return null;
    }

    const lng = Number(position[0]);
    const lat = Number(position[1]);

    return Number.isFinite(lng) && Number.isFinite(lat) ? [lng, lat] : null;
}

function isStrictPosition(position) {
    return Array.isArray(position)
        && position.length === 2
        && typeof position[0] === 'number'
        && typeof position[1] === 'number'
        && Number.isFinite(position[0])
        && Number.isFinite(position[1]);
}

function validateGeometry(geometry, lineCode = 'unknown') {
    const report = {
        lineCode,
        totalPositions: 0,
        invalidPositions: 0,
        emptyLineStrings: 0,
        singlePointLineStrings: 0,
        invalidGeometries: 0,
    };

    const validateLine = (line) => {
        if (! Array.isArray(line) || line.length === 0) {
            report.emptyLineStrings++;
            return;
        }

        if (line.length === 1) {
            report.singlePointLineStrings++;
        }

        line.forEach((position) => {
            report.totalPositions++;
            if (! isStrictPosition(position)) {
                report.invalidPositions++;
            }
        });
    };

    if (! geometry || ! ['LineString', 'MultiLineString'].includes(geometry.type)) {
        report.invalidGeometries++;
        return report;
    }

    if (geometry.type === 'LineString') {
        validateLine(geometry.coordinates);
    } else if (! Array.isArray(geometry.coordinates) || geometry.coordinates.length === 0) {
        report.invalidGeometries++;
    } else {
        geometry.coordinates.forEach(validateLine);
    }

    return report;
}

function lineStringsFromGeometry(geometry) {
    if (! geometry || ! ['LineString', 'MultiLineString'].includes(geometry.type)) {
        return [];
    }

    if (geometry.type === 'LineString') {
        const coordinates = (geometry.coordinates || []).map(normalizePosition).filter(Boolean);
        return coordinates.length > 1 ? [coordinates] : [];
    }

    return (geometry.coordinates || [])
        .map((line) => (Array.isArray(line) ? line : []).map(normalizePosition).filter(Boolean))
        .filter((line) => line.length > 1);
}

function featureFromLineString(coordinates, properties = {}) {
    return {
        type: 'Feature',
        properties,
        geometry: {
            type: 'LineString',
            coordinates,
        },
    };
}

function extractCoordinates(featureOrGeometry) {
    const geometry = featureOrGeometry?.geometry || featureOrGeometry;

    if (geometry?.type === 'LineString') {
        return (geometry.coordinates || []).map(normalizePosition).filter(Boolean);
    }

    if (geometry?.type === 'MultiLineString') {
        return (geometry.coordinates || []).flatMap((line) => (line || []).map(normalizePosition).filter(Boolean));
    }

    return [];
}

function isParisCoordinate([lng, lat]) {
    return lng > 1.5 && lng < 3.5 && lat > 48 && lat < 49.5;
}

function minimalFeatures() {
    return [featureFromLineString([[2.30, 48.85], [2.40, 48.87]], { color: '#ff0000' })];
}

function featuresForPayload(payload, mode) {
    if (mode === 'minimal') {
        return minimalFeatures();
    }

    const lineOne = payload.lines.find((line) => String(line.code) === '1');

    if (['line1-single', 'line1-no-properties'].includes(mode)) {
        const coordinates = lineStringsFromGeometry(lineOne?.path_geojson)[0] || [];
        const properties = mode === 'line1-no-properties' ? {} : { line_id: lineOne.id, code: lineOne.code, color: '#ff0000' };

        return coordinates.length > 1 ? [featureFromLineString(coordinates, properties)] : [];
    }

    if (mode === 'line1-multi') {
        return lineStringsFromGeometry(lineOne?.path_geojson).map((coordinates, index) => featureFromLineString(coordinates, {
            line_id: lineOne.id,
            code: lineOne.code,
            color: '#ff0000',
            segment: index,
        }));
    }

    return payload.lines.flatMap((line) => lineStringsFromGeometry(line.path_geojson).map((coordinates, index) => featureFromLineString(coordinates, {
        line_id: line.id,
        code: line.code,
        color: '#ff0000',
        segment: index,
    })));
}

function validationReport(payload) {
    const reports = payload.lines
        .filter((line) => line.path_geojson)
        .map((line) => validateGeometry(line.path_geojson, line.code));

    const total = reports.reduce((result, report) => ({
        totalPositions: result.totalPositions + report.totalPositions,
        invalidPositions: result.invalidPositions + report.invalidPositions,
        emptyLineStrings: result.emptyLineStrings + report.emptyLineStrings,
        singlePointLineStrings: result.singlePointLineStrings + report.singlePointLineStrings,
        invalidGeometries: result.invalidGeometries + report.invalidGeometries,
    }), {
        totalPositions: 0,
        invalidPositions: 0,
        emptyLineStrings: 0,
        singlePointLineStrings: 0,
        invalidGeometries: 0,
    });

    return { total, reports: reports.filter((report) => report.invalidPositions || report.emptyLineStrings || report.singlePointLineStrings || report.invalidGeometries) };
}

function fitToFeature(map, feature) {
    const coordinates = extractCoordinates(feature);

    if (coordinates.length < 2) {
        return;
    }

    const bounds = coordinates.reduce(
        (result, coordinate) => result.extend(coordinate),
        new maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
    );

    map.fitBounds(bounds, { padding: 50, duration: 0 });
}

async function runDiagnostic() {
    const root = document.getElementById('map-line-diagnostic');
    const status = document.getElementById('map-line-diagnostic-status');
    const dataset = root.dataset;
    const mode = params.get('dataset') || 'all';
    const config = {
        rasterUrl: dataset.rasterUrl || '',
        rasterTileSize: number(dataset.rasterTileSize, 256),
        attribution: dataset.mapAttribution || '',
        center: [number(dataset.mapCenterLongitude, 2.3522), number(dataset.mapCenterLatitude, 48.8566)],
        zoom: number(dataset.mapZoom, 11.5),
        maxZoom: number(dataset.mapMaxZoom, 19),
    };

    console.log('[fotometro diagnostic] maplibre version', maplibregl.getVersion?.() ?? 'unknown');

    const map = new maplibregl.Map({
        container: 'map-line-diagnostic-canvas',
        style: buildBaseStyle(config),
        center: config.center,
        zoom: config.zoom,
        maxZoom: config.maxZoom,
        attributionControl: false,
    });

    window.fotometroLineDiagnostic = { map, config, diagnosticErrors };

    map.on('error', (event) => {
        recordError('[fotometro diagnostic] MAP ERROR', {
            message: event?.error?.message,
            stack: event?.error?.stack,
            sourceId: event?.sourceId,
            tile: event?.tile,
            raw: event,
        });
    });
    map.on('styledata', () => console.debug('[fotometro diagnostic] styledata', map.isStyleLoaded()));
    map.on('sourcedata', (event) => {
        if (['fotometro-basemap', 'fotometro-lines'].includes(event.sourceId)) {
            console.debug('[fotometro diagnostic] sourcedata', {
                sourceId: event.sourceId,
                isSourceLoaded: event.isSourceLoaded,
                sourceDataType: event.sourceDataType,
            });
        }
    });

    map.once('load', async () => {
        const payload = mode === 'minimal'
            ? { lines: [] }
            : await fetch(dataset.mapEndpoint, { headers: { Accept: 'application/json' } }).then((response) => response.json());
        const features = featuresForPayload(payload, mode);
        const lineGeoJson = { type: 'FeatureCollection', features };
        const validation = mode === 'minimal' ? null : validationReport(payload);
        const coordinates = features.flatMap(extractCoordinates);

        console.debug('[fotometro diagnostic] validation', validation);
        console.debug('[fotometro diagnostic] mode', mode);
        console.debug('[fotometro diagnostic] feature count', features.length);
        console.debug('[fotometro diagnostic] first feature', features[0] || null);
        console.debug('[fotometro diagnostic] first coordinate', coordinates[0] || null);
        console.debug('[fotometro diagnostic] plausible coordinates', coordinates.filter(isParisCoordinate).length);

        map.addSource('fotometro-lines', {
            type: 'geojson',
            data: lineGeoJson,
        });
        map.addLayer({
            id: 'fotometro-lines-layer',
            type: 'line',
            source: 'fotometro-lines',
            layout: {
                visibility: 'visible',
                'line-cap': 'round',
                'line-join': 'round',
            },
            paint: {
                'line-color': '#ff0000',
                'line-width': 12,
                'line-opacity': 1,
            },
        });

        fitToFeature(map, features[0]);
        status.textContent = `Mode: ${mode} - features: ${features.length} - première coordonnée: ${JSON.stringify(coordinates[0] || null)}`;

        let finalized = false;
        const finalize = (reason) => {
            if (finalized) {
                return;
            }

            finalized = true;
            const layers = map.getStyle().layers.map((layer) => layer.id);
            const renderedFeatures = map.getLayer('fotometro-lines-layer')
                ? map.queryRenderedFeatures(undefined, { layers: ['fotometro-lines-layer'] }).length
                : null;
            const result = {
                mode,
                reason,
                errors: diagnosticErrors,
                sourceLoaded: map.getSource('fotometro-lines') ? map.isSourceLoaded('fotometro-lines') : null,
                sourceFeatures: map.getSource('fotometro-lines') ? map.querySourceFeatures('fotometro-lines').length : null,
                renderedFeatures,
                visible: renderedFeatures > 0,
                basemapLoaded: map.getSource('fotometro-basemap') ? map.isSourceLoaded('fotometro-basemap') : null,
                styleLoaded: map.isStyleLoaded(),
                layerOrder: {
                    basemap: layers.indexOf('fotometro-basemap'),
                    background: layers.indexOf('background'),
                    lines: layers.indexOf('fotometro-lines-layer'),
                },
                validation,
            };

            console.table({
                mode: result.mode,
                reason: result.reason,
                sourceLoaded: result.sourceLoaded,
                sourceFeatures: result.sourceFeatures,
                renderedFeatures: result.renderedFeatures,
                visible: result.visible,
                basemapLoaded: result.basemapLoaded,
                styleLoaded: result.styleLoaded,
            });
            console.log('[fotometro diagnostic] result', result);
            window.fotometroLineDiagnostic.result = result;
        };

        map.once('idle', () => finalize('idle'));
        setTimeout(() => finalize('timeout'), 5000);
    });
}

runDiagnostic().catch((error) => {
    recordError('[fotometro diagnostic] FAILED', {
        message: error.message,
        stack: error.stack,
        raw: error,
    });
    document.getElementById('map-line-diagnostic-status').textContent = error.message || String(error);
});
