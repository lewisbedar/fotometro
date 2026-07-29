import 'maplibre-gl/dist/maplibre-gl.css';

const maplibreWorkerUrl = '/vendor/maplibre-gl/maplibre-gl-worker.mjs';

let maplibrePromise = null;

async function loadMapLibre() {
    maplibrePromise ??= import('maplibre-gl').then((maplibregl) => {
        if (! maplibregl.getWorkerUrl?.()) {
            maplibregl.setWorkerUrl?.(maplibreWorkerUrl);
        }

        return maplibregl;
    });

    return maplibrePromise;
}

function resolveMaxZoom(styleUrl, configuredMaxZoom) {
    if (configuredMaxZoom !== null && configuredMaxZoom !== undefined && configuredMaxZoom !== '') {
        return Number(configuredMaxZoom);
    }

    if (styleUrl.includes('demotiles.maplibre.org')) {
        return 5;
    }

    return 19;
}

function normalizeBasemapDriver(driver) {
    return ['raster', 'style'].includes(driver) ? driver : 'raster';
}

function buildRasterStyle(config) {
    return {
        version: 8,
        sources: {
            basemap: {
                type: 'raster',
                tiles: [config.rasterUrl],
                tileSize: Number(config.rasterTileSize ?? 256),
                minzoom: 0,
                maxzoom: Number(config.maxZoom ?? 19),
                attribution: config.attribution ?? '',
            },
        },
        layers: [
            {
                id: 'fotometro-basemap',
                type: 'raster',
                source: 'basemap',
                minzoom: 0,
                maxzoom: Number(config.maxZoom ?? 19),
            },
        ],
    };
}

function buildMapConfig(dataset) {
    const basemapDriver = normalizeBasemapDriver(dataset.basemapDriver || 'raster');
    const configuredMaxZoom = dataset.mapMaxZoom ? Number(dataset.mapMaxZoom) : null;
    const styleUrl = dataset.mapStyle || '';
    const maxZoom = basemapDriver === 'raster'
        ? Number(configuredMaxZoom ?? 19)
        : resolveMaxZoom(styleUrl, configuredMaxZoom);

    return {
        basemapDriver,
        styleUrl,
        rasterUrl: dataset.rasterUrl || '',
        rasterTileSize: Number(dataset.rasterTileSize || 256),
        attribution: dataset.mapAttribution || '',
        centerLongitude: Number(dataset.mapCenterLongitude || 2.3522),
        centerLatitude: Number(dataset.mapCenterLatitude || 48.8566),
        zoom: Number(dataset.mapZoom || 11.5),
        maxZoom,
        hasBasemapConfig: basemapDriver === 'raster' ? Boolean(dataset.rasterUrl) : Boolean(dataset.mapStyle),
    };
}

function resolveMapStyle(config) {
    return config.basemapDriver === 'raster'
        ? buildRasterStyle(config)
        : config.styleUrl;
}

function basemapConfigurationMessage(config) {
    return config.basemapDriver === 'raster'
        ? 'Fond de carte raster absent: renseignez FOTOMETRO_MAP_RASTER_URL.'
        : 'Style MapLibre absent: renseignez FOTOMETRO_MAP_STYLE_URL.';
}

function describeMapError(event) {
    const candidate = event?.error ?? event;

    if (candidate instanceof Error) {
        return {
            name: candidate.name || 'Error',
            message: candidate.message || 'Erreur MapLibre inconnue',
            stack: candidate.stack ?? null,
            raw: event,
        };
    }

    if (typeof candidate === 'string') {
        return {
            name: 'MapLibreError',
            message: candidate,
            stack: null,
            raw: event,
        };
    }

    try {
        return {
            name: 'MapLibreError',
            message: JSON.stringify(candidate ?? event ?? {}),
            stack: null,
            raw: event,
        };
    } catch {
        return {
            name: 'MapLibreError',
            message: 'Evenement MapLibre non serialisable',
            stack: null,
            raw: event,
        };
    }
}

window.fotometroMapExplorer = function fotometroMapExplorer(dataset) {
    let mapInstance = null;
    let maplibreglInstance = null;
    let stationPopup = null;
    let stationPopupOpenZoom = null;

    return {
        mapData: { lines: [], stations: [], coverage_statuses: [] },
        mapEndpoint: dataset.mapEndpoint,
        searchEndpoint: dataset.searchEndpoint,
        basemapConfig: buildMapConfig(dataset),
        appEnv: dataset.appEnv || 'production',
        selectedLineId: null,
        selectedStationId: null,
        selectedStation: null,
        activePanel: null,
        isFiltersOpen: false,
        isLinesOpen: false,
        isLineDiagramOpen: false,
        progressCollapsed: true,
        showStations: true,
        showLineTracks: true,
        showConnections: true,
        showEntrances: false,
        enabledStatuses: ['not_started', 'planned', 'in_progress', 'documented', 'complete'],
        searchQuery: '',
        searchResults: [],
        searchLineResults: [],
        focusedSearchIndex: -1,
        searchLoading: false,
        searchTimer: null,
        searchError: null,
        searchRequestSequence: 0,
        searchRequestController: null,
        isAboutOpen: false,
        isBetaNoticeOpen: false,
        mapFatalError: null,
        mapWarnings: [],
        mapHasLoaded: false,
        mapStyleHasLoaded: false,
        lineDiagramError: null,
        lineLayerDiagnostics: null,

        async init() {
            this.isBetaNoticeOpen = ! this.hasSeenBetaNotice();

            try {
                await this.loadMapData();

                if (this.hasBasemapConfig) {
                    await this.createMap();
                } else if (this.isLocal) {
                    console.error('[fotometro] Map configuration missing', basemapConfigurationMessage(this.basemapConfig));
                }
            } catch (error) {
                this.reportFatalMapError('Map initialization failed', error);
            }
        },

        get isLocal() {
            return this.appEnv === 'local';
        },

        get config() {
            return {
                ...this.basemapConfig,
                mapEndpoint: this.mapEndpoint,
                searchEndpoint: this.searchEndpoint,
            };
        },

        get hasBasemapConfig() {
            return this.basemapConfig.hasBasemapConfig;
        },

        get mapAttribution() {
            return this.basemapConfig.attribution;
        },

        get mapUsableMaxZoom() {
            return this.basemapConfig.maxZoom;
        },

        get selectedLine() {
            if (! this.selectedLineId) {
                return null;
            }

            return this.mapData.lines.find((line) => Number(line.id) === Number(this.selectedLineId)) || null;
        },

        get hasSelectedLineLayout() {
            return this.hasLayoutForLine(this.selectedLine);
        },

        get selectedLineLayout() {
            return this.hasSelectedLineLayout ? this.selectedLine.topology.layout : null;
        },

        get allStatusesEnabled() {
            return this.mapData.coverage_statuses.length > 0
                && this.mapData.coverage_statuses.every((status) => this.enabledStatuses.includes(status.value));
        },

        get isSmallScreen() {
            return window.matchMedia('(max-width: 767px)').matches;
        },

        get initialMapZoom() {
            return Math.min(this.basemapConfig.zoom, this.mapUsableMaxZoom);
        },

        get debugLinesEnabled() {
            return this.isLocal && new URLSearchParams(window.location.search).get('debugLines') === '1';
        },

        getMap() {
            return mapInstance;
        },

        getMapLibre() {
            return maplibreglInstance;
        },

        async loadMapData() {
            const response = await fetch(this.mapEndpoint, {
                headers: { Accept: 'application/json' },
            });

            this.mapData = await response.json();
        },

        async createMap() {
            if (mapInstance) {
                return;
            }

            maplibreglInstance = await loadMapLibre();
            const container = document.getElementById('metro-map');

            if (! container) {
                throw new Error('Map container #metro-map was not found.');
            }

            if (container.__fotometroMapInstance) {
                mapInstance = container.__fotometroMapInstance;
                return;
            }

            if (! this.hasBasemapConfig) {
                return;
            }

            try {
                const map = new maplibreglInstance.Map({
                    container,
                    style: resolveMapStyle(this.basemapConfig),
                    center: [
                        this.config.centerLongitude,
                        this.config.centerLatitude,
                    ],
                    zoom: this.initialMapZoom,
                    maxZoom: this.mapUsableMaxZoom,
                    attributionControl: false,
                });
                mapInstance = map;
                container.__fotometroMapInstance = map;

                if (this.isLocal || import.meta.env.DEV) {
                    console.debug('[fotometro] map constructed', {
                        styleLoaded: map.isStyleLoaded(),
                        mapProxied: false,
                        rawMap: window.Alpine?.raw ? window.Alpine.raw(map) : null,
                    });
                    console.log('map proxied?', map);
                    console.log('raw map', window.Alpine?.raw ? window.Alpine.raw(map) : null);
                }

                if (this.basemapConfig.attribution) {
                    map.addControl(new maplibreglInstance.AttributionControl({
                        customAttribution: this.basemapConfig.attribution,
                        compact: true,
                    }));
                }

                map.on('style.load', () => {
                    this.mapStyleHasLoaded = true;
                });

                map.on('zoomend', () => this.handleMapZoomEnd());

                if (this.isLocal || import.meta.env.DEV) {
                    map.on('styledata', () => {
                        console.debug('[fotometro] styledata', map.isStyleLoaded());
                    });

                    map.on('sourcedata', (event) => {
                        if (['fotometro-lines', 'fotometro-basemap'].includes(event.sourceId)) {
                            console.debug('[fotometro] sourcedata', {
                                sourceId: event.sourceId,
                                isSourceLoaded: event.isSourceLoaded,
                                sourceDataType: event.sourceDataType,
                            });
                        }
                    });
                }

                map.once('load', () => {
                    this.mapHasLoaded = true;
                    this.mapStyleHasLoaded = true;

                    console.debug('[fotometro] map load fired');

                    if (import.meta.env.DEV) {
                        console.debug('[fotometro] map loaded', {
                            center: map.getCenter(),
                            zoom: map.getZoom(),
                            basemapDriver: this.basemapConfig.basemapDriver,
                        });
                    }

                    const canvas = map.getCanvas();

                    if (canvas.width === 0 || canvas.height === 0 || canvas.clientWidth === 0 || canvas.clientHeight === 0) {
                        this.reportFatalMapError('Map canvas has no drawable size', 'Canvas dimensions are zero.');
                    }

                    map.resize();
                    this.addSourcesAndLayers();
                    this.fitToVisibleData();

                    requestAnimationFrame(() => map.resize());
                });

                map.once('idle', () => {
                    console.debug('[fotometro] first idle fired');
                    console.debug('[fotometro] idle styleLoaded', map.isStyleLoaded());
                    console.debug('[fotometro] basemap loaded', map.getSource('fotometro-basemap') ? map.isSourceLoaded('fotometro-basemap') : null);
                    console.debug('[fotometro] lines loaded', map.getSource('fotometro-lines') ? map.isSourceLoaded('fotometro-lines') : null);
                    console.debug('[fotometro] rendered lines after idle', map.getLayer('fotometro-lines-layer')
                        ? map.queryRenderedFeatures(undefined, { layers: ['fotometro-lines-layer'] }).length
                        : null);
                });

                map.on('error', (event) => {
                    const details = describeMapError(event);

                    if (this.isLocal || import.meta.env.DEV) {
                        console.error('[fotometro] MapLibre error', event?.error ?? event);
                        console.error('[fotometro] raw MapLibre error event', event);
                        console.error('[fotometro] normalized MapLibre error', details);
                    }

                    if (this.mapHasLoaded || this.mapStyleHasLoaded) {
                        this.mapWarnings.push(details.message);
                        return;
                    }

                    this.mapWarnings.push(details.message);
                });

                setTimeout(() => {
                    if (! mapInstance) {
                        return;
                    }

                    if (! this.mapHasLoaded && ! this.mapStyleHasLoaded) {
                        this.reportFatalMapError('MapLibre did not finish loading', 'No load or style.load event after 5 seconds.');
                    }
                }, 5000);

                requestAnimationFrame(() => mapInstance?.resize());
            } catch (error) {
                this.reportFatalMapError('Map initialization failed', error);
            }
        },

        addSourcesAndLayers() {
            if (this.getMap().getSource('fotometro-lines')) {
                this.refreshVisibility();
                return;
            }

            const lineGeoJson = this.lineFeatureCollection();
            const geometryTypes = lineGeoJson.features.map((feature) => feature.geometry?.type).filter(Boolean);
            const firstFeature = lineGeoJson.features[0] ?? null;
            const firstCoordinate = this.extractLineCoordinates(firstFeature)[0] ?? null;
            const plausibleCoordinates = lineGeoJson.features
                .flatMap((feature) => this.extractLineCoordinates(feature))
                .filter((coordinate) => this.isPlausibleParisCoordinate(coordinate));

            if (import.meta.env.DEV) {
                console.debug('[fotometro] line geojson', lineGeoJson);
                console.debug('[fotometro] line feature count', lineGeoJson.features?.length);
                console.debug('[fotometro] line geometry types', [...new Set(geometryTypes)]);
                console.debug('[fotometro] first line properties', firstFeature?.properties ?? null);
                console.debug('[fotometro] first line geometry', JSON.stringify(firstFeature?.geometry ?? null).slice(0, 1000));
                console.debug('[fotometro] first line coordinate', firstCoordinate);
                console.debug('[fotometro] plausible Paris coordinates', plausibleCoordinates.length);
            }

            this.getMap().addSource('fotometro-lines', {
                type: 'geojson',
                data: lineGeoJson,
            });

            this.getMap().addSource('fotometro-stations', {
                type: 'geojson',
                data: this.stationFeatureCollection(),
            });

            this.getMap().addLayer({
                id: 'fotometro-lines-layer',
                type: 'line',
                source: 'fotometro-lines',
                layout: {
                    visibility: this.showLineTracks ? 'visible' : 'none',
                    'line-cap': 'round',
                    'line-join': 'round',
                },
                paint: {
                    'line-color': this.debugLinesEnabled ? '#ff0000' : ['coalesce', ['get', 'color'], '#ff0000'],
                    'line-width': this.debugLinesEnabled ? 12 : ['case', ['==', ['get', 'selected'], true], 8, 5],
                    'line-opacity': this.debugLinesEnabled ? 1 : ['case', ['==', ['get', 'dimmed'], true], 0.25, 0.95],
                },
            });

            if (import.meta.env.DEV) {
                console.debug('[fotometro] lines layer', this.getMap().getLayer('fotometro-lines-layer'));
                console.debug('[fotometro] lines source', this.getMap().getSource('fotometro-lines'));
            }

            this.getMap().addLayer({
                id: 'fotometro-stations-layer',
                type: 'circle',
                source: 'fotometro-stations',
                paint: {
                    'circle-radius': ['case', ['==', ['get', 'selected'], true], 10, 7],
                    'circle-color': ['get', 'status_color'],
                    'circle-stroke-color': ['case', ['==', ['get', 'selected'], true], '#151515', '#ffffff'],
                    'circle-stroke-width': ['case', ['==', ['get', 'selected'], true], 4, 2],
                    'circle-opacity': ['case', ['==', ['get', 'dimmed'], true], 0.25, 0.95],
                },
            });

            this.enforceMapLayerOrder();
            this.queueLineLayerDiagnostic(lineGeoJson);

            // A single handler for the whole canvas, rather than one
            // `on('click', layerId, ...)` per layer: a station always sits
            // on top of its own line's path, so both layers match the same
            // point and independent handlers would both fire - selectLine()
            // would then immediately clobber the station selection that
            // selectStation() had just set. Station wins when both match;
            // an empty map background (neither) closes open panels.
            this.getMap().on('click', (event) => {
                const stationFeatures = this.getMap().queryRenderedFeatures(event.point, { layers: ['fotometro-stations-layer'] });
                const stationId = stationFeatures[0]?.properties?.station_id;

                if (stationId) {
                    this.selectStation(Number(stationId), false);
                    return;
                }

                const lineFeatures = this.getMap().queryRenderedFeatures(event.point, { layers: ['fotometro-lines-layer'] });
                const lineId = lineFeatures[0]?.properties?.line_id;

                if (lineId) {
                    this.selectLine(Number(lineId));
                    return;
                }

                this.closeAllPanels();
            });

            this.getMap().on('mouseenter', 'fotometro-lines-layer', () => {
                this.getMap().getCanvas().style.cursor = 'pointer';
            });

            this.getMap().on('mouseleave', 'fotometro-lines-layer', () => {
                this.getMap().getCanvas().style.cursor = '';
            });

            this.getMap().on('mouseenter', 'fotometro-stations-layer', () => {
                this.getMap().getCanvas().style.cursor = 'pointer';
            });

            this.getMap().on('mouseleave', 'fotometro-stations-layer', () => {
                this.getMap().getCanvas().style.cursor = '';
            });
        },

        enforceMapLayerOrder() {
            if (! this.getMap()) {
                return;
            }

            if (this.getMap().getLayer('fotometro-lines-layer') && this.getMap().getLayer('fotometro-stations-layer')) {
                this.getMap().moveLayer('fotometro-lines-layer', 'fotometro-stations-layer');
            }

            if (this.getMap().getLayer('fotometro-stations-layer')) {
                this.getMap().moveLayer('fotometro-stations-layer');
            }
        },

        queueLineLayerDiagnostic(lineGeoJson) {
            if (! (this.isLocal || import.meta.env.DEV)) {
                return;
            }

            const run = () => this.logLineLayerDiagnostic(lineGeoJson);

            this.getMap().once('idle', run);
            requestAnimationFrame(() => requestAnimationFrame(run));

            if (this.debugLinesEnabled) {
                const lineOne = this.mapData.lines.find((line) => String(line.code) === '1');
                const coordinates = this.extractLineCoordinates(this.normalizeLineGeoJson(lineOne?.path_geojson));

                if (coordinates.length > 1) {
                    this.fitToCoordinates(coordinates);
                }
            }
        },

        logLineLayerDiagnostic(lineGeoJson) {
            if (! this.getMap()) {
                return;
            }

            const layers = this.getMap().getStyle()?.layers?.map((layer) => layer.id) ?? [];
            const layerExists = Boolean(this.getMap().getLayer('fotometro-lines-layer'));
            const layerOrder = {
                basemap: layers.indexOf('fotometro-basemap'),
                lines: layers.indexOf('fotometro-lines-layer'),
                stations: layers.indexOf('fotometro-stations-layer'),
            };
            const renderedFeatures = layerExists
                ? this.getMap().queryRenderedFeatures(undefined, { layers: ['fotometro-lines-layer'] }).length
                : 0;
            const firstCoordinate = this.extractLineCoordinates(lineGeoJson.features?.[0])[0] ?? null;

            this.lineLayerDiagnostics = {
                sourceExists: Boolean(this.getMap().getSource('fotometro-lines')),
                layerExists,
                renderedFeatures,
                firstCoordinate,
                layerOrder,
            };

            console.group('[fotometro] line layer diagnostic');
            console.log('style loaded', this.getMap().isStyleLoaded());
            console.log('source', this.getMap().getSource('fotometro-lines'));
            console.log('layer', this.getMap().getLayer('fotometro-lines-layer'));
            console.log('layout visibility', layerExists ? this.getMap().getLayoutProperty('fotometro-lines-layer', 'visibility') : null);
            console.log('line width', layerExists ? this.getMap().getPaintProperty('fotometro-lines-layer', 'line-width') : null);
            console.log('line opacity', layerExists ? this.getMap().getPaintProperty('fotometro-lines-layer', 'line-opacity') : null);
            console.log('rendered line features', renderedFeatures);
            console.log('layer order', layerOrder);
            console.log('layers', layers);
            console.log('first properties', lineGeoJson.features?.[0]?.properties ?? null);
            console.log('first geometry', JSON.stringify(lineGeoJson.features?.[0]?.geometry ?? null).slice(0, 1000));
            console.log('first coordinate', firstCoordinate);
            console.log('plausible Paris coordinates', lineGeoJson.features
                .flatMap((feature) => this.extractLineCoordinates(feature))
                .filter((coordinate) => this.isPlausibleParisCoordinate(coordinate)).length);
            console.groupEnd();
        },

        isPlausibleParisCoordinate(coordinate) {
            const lng = Number(coordinate?.[0]);
            const lat = Number(coordinate?.[1]);

            return lng > 1.5
                && lng < 3.5
                && lat > 48
                && lat < 49.5;
        },

        lineFeatureCollection() {
            return {
                type: 'FeatureCollection',
                features: this.mapData.lines
                    .filter((line) => this.selectedLineId === null || Number(this.selectedLineId) === Number(line.id))
                    .flatMap((line) => this.normalizeLineGeoJsonFeatures(line.path_geojson).map((feature) => ({
                        line,
                        feature,
                    })))
                    .map(({ line, feature }) => ({
                        ...feature,
                        properties: {
                            line_id: line.id,
                            code: line.code,
                            color: line.color,
                            selected: Number(this.selectedLineId) === Number(line.id),
                            dimmed: false,
                        },
                    })),
            };
        },

        normalizeLineGeoJson(pathGeojson) {
            return this.normalizeLineGeoJsonFeatures(pathGeojson)[0] ?? null;
        },

        normalizeLineGeoJsonFeatures(pathGeojson) {
            if (! pathGeojson || typeof pathGeojson !== 'object') {
                return [];
            }

            if (pathGeojson.type === 'Feature' && pathGeojson.geometry) {
                return this.normalizeLineGeometry(pathGeojson.geometry).map((geometry) => ({
                    type: 'Feature',
                    geometry,
                    properties: {},
                }));
            }

            if (['LineString', 'MultiLineString'].includes(pathGeojson.type)) {
                return this.normalizeLineGeometry(pathGeojson).map((geometry) => ({
                    type: 'Feature',
                    geometry,
                    properties: {},
                }));
            }

            if (pathGeojson.type === 'FeatureCollection' && Array.isArray(pathGeojson.features)) {
                return pathGeojson.features
                    .flatMap((feature) => this.normalizeLineGeoJsonFeatures(feature))
                    .filter(Boolean);
            }

            return [];
        },

        normalizeLineGeometry(geometry) {
            if (! geometry || ! ['LineString', 'MultiLineString'].includes(geometry.type)) {
                return [];
            }

            if (geometry.type === 'MultiLineString') {
                return (geometry.coordinates ?? [])
                    .map((line) => (Array.isArray(line) ? line : [])
                        .map((coordinate) => this.normalizeCoordinate(coordinate))
                        .filter(Boolean))
                    .filter((line) => line.length > 1)
                    .map((coordinates) => ({ type: 'LineString', coordinates }));
            }

            const coordinates = (geometry.coordinates ?? [])
                .map((coordinate) => this.normalizeCoordinate(coordinate))
                .filter(Boolean);

            return coordinates.length > 1 ? [{ type: 'LineString', coordinates }] : [];
        },

        normalizeCoordinate(coordinate) {
            if (! Array.isArray(coordinate) || coordinate.length < 2) {
                return null;
            }

            const longitude = Number(coordinate[0]);
            const latitude = Number(coordinate[1]);

            return Number.isFinite(longitude) && Number.isFinite(latitude)
                ? [longitude, latitude]
                : null;
        },

        stationFeatureCollection() {
            return {
                type: 'FeatureCollection',
                features: this.visibleStations().map((station) => ({
                    type: 'Feature',
                    geometry: {
                        type: 'Point',
                        coordinates: station.coordinates,
                    },
                    properties: {
                        station_id: station.id,
                        name: station.name,
                        status: station.coverage_status.value,
                        status_color: station.coverage_status.color,
                        line_ids: this.normalizeLines(station.lines).map((line) => line.id).join(','),
                        selected: this.selectedStation?.id === station.id,
                        dimmed: this.selectedLineId !== null && ! this.normalizeLines(station.lines).some((line) => line.id === this.selectedLineId),
                    },
                })),
            };
        },

        visibleStations() {
            return this.mapData.stations.filter((station) => {
                const statusIsEnabled = this.enabledStatuses.includes(station.coverage_status.value);
                const lineIsVisible = this.selectedLineId === null || this.normalizeLines(station.lines).some((line) => line.id === this.selectedLineId);

                return statusIsEnabled && lineIsVisible;
            });
        },

        refreshVisibility() {
            if (! this.getMap()) {
                return;
            }

            this.getMap().getSource('fotometro-lines')?.setData(this.lineFeatureCollection());
            this.getMap().getSource('fotometro-stations')?.setData(this.stationFeatureCollection());
            this.refreshLayerVisibility();
        },

        refreshLayerVisibility() {
            if (! this.getMap()) {
                return;
            }

            if (this.getMap().getLayer('fotometro-stations-layer')) {
                this.getMap().setLayoutProperty('fotometro-stations-layer', 'visibility', this.showStations ? 'visible' : 'none');
            }

            if (this.getMap().getLayer('fotometro-lines-layer')) {
                this.getMap().setLayoutProperty('fotometro-lines-layer', 'visibility', this.showLineTracks ? 'visible' : 'none');
            }
        },

        toggleFiltersPanel() {
            this.isFiltersOpen = ! this.isFiltersOpen;

            if (this.isFiltersOpen) {
                this.isLinesOpen = false;
                this.activePanel = 'filters';
                this.clearLineSelection();
            } else if (this.activePanel === 'filters') {
                this.activePanel = null;
            }
        },

        toggleLinesPanel() {
            this.isLinesOpen = ! this.isLinesOpen;

            if (this.isLinesOpen) {
                this.isFiltersOpen = false;
                this.activePanel = 'lines';
                this.clearLineSelection();
            } else if (this.activePanel === 'lines') {
                this.activePanel = null;
            }
        },

        toggleAllStatuses(enabled) {
            this.enabledStatuses = enabled
                ? this.mapData.coverage_statuses.map((status) => status.value)
                : [];
            this.refreshVisibility();
        },

        selectLine(lineId) {
            const normalizedLineId = Number(lineId);

            if (Number(this.selectedLineId) === normalizedLineId) {
                this.clearLineSelection();
                return;
            }

            const line = this.mapData.lines.find((candidate) => Number(candidate.id) === normalizedLineId);

            if (! line) {
                return;
            }

            this.selectedLineId = line.id;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.lineDiagramError = null;
            this.refreshVisibility();

            if (! this.hasLayoutForLine(line)) {
                this.lineDiagramError = 'Données de plan incomplètes pour cette ligne.';

                if (import.meta.env.DEV) {
                    console.error('[fotometro] invalid line diagram layout', { line });
                }
            }

            // The drawer starts closed: it used to open automatically and
            // would often cover part of the line info panel. It renders
            // lazily on first open (see toggleLineDiagram()).
            this.isLineDiagramOpen = false;
            this.isLinesOpen = false;
            this.activePanel = null;
            this.fitMapToLine(line);
        },

        // The diagram panel is a drawer: it stays visible (as a slim header
        // bar) whenever a line is selected, and this only toggles whether
        // its body is expanded. Re-render on expand since the SVG host is
        // torn down (x-if) while collapsed.
        toggleLineDiagram() {
            this.isLineDiagramOpen = ! this.isLineDiagramOpen;

            if (this.isLineDiagramOpen) {
                this.renderSelectedLineDiagram();
            } else {
                // sizeDiagramPanelToContent() sets an inline width sized to the last
                // diagram's content; without clearing it, the collapsed handle bar
                // keeps that width instead of returning to the CSS default.
                document.querySelector('.line-diagram-panel')?.style.removeProperty('width');
            }
        },

        selectStation(stationId, fromSearch = false) {
            const station = this.findStation(stationId) || this.searchResults.find((result) => result.id === stationId);

            if (! station) {
                return;
            }

            this.selectedStation = station;
            this.selectedStationId = station.id;
            this.isFiltersOpen = false;
            this.isLinesOpen = false;
            this.activePanel = null;
            this.refreshVisibility();

            if (this.getMap() && station.coordinates) {
                this.$nextTick(() => {
                    this.getMap().flyTo({
                        center: station.coordinates,
                        zoom: Math.min(Math.max(this.getMap().getZoom(), fromSearch ? 14 : 13), this.mapUsableMaxZoom),
                        padding: this.mapPaddingForOpenPanels(40),
                        essential: false,
                    });
                });
                this.openStationPopup(station);
            }

            this.scrollDiagramToStation(station.id);
            this.renderSelectedLineDiagram();
        },

        findStation(stationId) {
            return this.mapData.stations.find((station) => station.id === stationId);
        },

        openStationPopup(station) {
            this.closeStationPopup();

            const maplibregl = this.getMapLibre();
            const content = document.createElement('div');

            if (station.cover_photo_url) {
                const cover = document.createElement('img');
                cover.src = station.cover_photo_url;
                cover.alt = '';
                cover.className = 'station-popup-cover';
                content.appendChild(cover);
            }

            const title = document.createElement('h3');
            title.className = 'station-popup-title text-base font-semibold';
            title.textContent = station.name;
            content.appendChild(title);

            const subtitle = document.createElement('p');
            subtitle.className = 'text-xs text-black/55';
            subtitle.textContent = station.district || station.city || '';
            if (subtitle.textContent) {
                content.appendChild(subtitle);
            }

            const lines = document.createElement('div');
            lines.className = 'mt-3 flex flex-wrap gap-1';
            this.normalizeLines(station.lines).forEach((line) => {
                const badge = document.createElement('span');
                badge.className = 'line-code';
                badge.style.background = this.safeLineColor(line.color);
                badge.style.color = this.safeLineColor(line.text_color);
                badge.textContent = line.code;
                lines.appendChild(badge);
            });
            content.appendChild(lines);

            const status = document.createElement('p');
            status.className = 'station-popup-status mt-3 text-sm';
            const statusDot = document.createElement('span');
            statusDot.className = 'station-popup-status-dot';
            statusDot.style.background = this.safeLineColor(station.coverage_status.color);
            status.appendChild(statusDot);
            const statusLabel = Number.isFinite(station.coverage_percentage)
                ? `${station.coverage_status.description} (${station.coverage_percentage} %)`
                : station.coverage_status.description;
            status.appendChild(document.createTextNode(statusLabel));
            content.appendChild(status);

            const link = document.createElement('a');
            link.href = station.url;
            link.className = 'mt-3 inline-flex min-h-9 w-full items-center justify-center rounded-md border border-black/15 bg-white px-4 text-sm font-semibold hover:bg-black hover:text-white';
            link.textContent = 'Voir la station';
            content.appendChild(link);

            const popup = new maplibregl.Popup({ closeButton: true, closeOnClick: false, maxWidth: 'none' })
                .setLngLat(station.coordinates)
                .setDOMContent(content)
                .addTo(this.getMap());

            popup.on('close', () => {
                if (Number(this.selectedStationId) === Number(station.id)) {
                    this.selectedStation = null;
                    this.selectedStationId = null;
                    this.refreshVisibility();
                    this.renderSelectedLineDiagram();
                }
            });

            stationPopup = popup;
            stationPopupOpenZoom = this.getMap().getZoom();
        },

        closeStationPopup() {
            if (! stationPopup) {
                return;
            }

            const popup = stationPopup;
            stationPopup = null;
            stationPopupOpenZoom = null;
            popup.remove();
        },

        handleMapZoomEnd() {
            if (stationPopupOpenZoom === null || ! this.getMap()) {
                return;
            }

            if (this.getMap().getZoom() < stationPopupOpenZoom - 0.01) {
                this.clearStationSelection();
            }
        },

        clearSelection() {
            this.clearStationSelection();
        },

        clearStationSelection() {
            this.selectedStation = null;
            this.selectedStationId = null;
            this.closeStationPopup();
            this.refreshVisibility();
            this.renderSelectedLineDiagram();
        },

        clearLineSelection() {
            this.selectedLineId = null;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.isLineDiagramOpen = false;
            this.lineDiagramError = null;
            this.closeStationPopup();
            this.clearRenderedLineDiagram();
            this.refreshVisibility();
            this.fitToVisibleData();
        },

        // "Click outside to dismiss": closes every floating panel without
        // touching filter preferences or the search query, unlike
        // resetFilters() which is a full reset back to defaults.
        closeAllPanels() {
            this.isFiltersOpen = false;
            this.isLinesOpen = false;
            this.activePanel = null;
            this.clearLineSelection();
        },

        resetFilters() {
            this.selectedLineId = null;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.enabledStatuses = this.mapData.coverage_statuses.map((status) => status.value);
            this.searchQuery = '';
            this.searchResults = [];
            this.searchLineResults = [];
            this.focusedSearchIndex = -1;
            this.isFiltersOpen = false;
            this.isLinesOpen = false;
            this.isLineDiagramOpen = false;
            this.lineDiagramError = null;
            this.activePanel = null;
            this.closeStationPopup();
            this.clearRenderedLineDiagram();
            this.refreshVisibility();
            this.fitToVisibleData();
        },

        resetExplorer() {
            this.showStations = true;
            this.showLineTracks = true;
            this.showConnections = true;
            this.resetFilters();
        },

        fitToVisibleData() {
            if (! this.getMap()) {
                return;
            }

            const maplibregl = this.getMapLibre();
            const coordinates = [];

            this.visibleStations().forEach((station) => {
                if (station.coordinates) {
                    coordinates.push(station.coordinates);
                }
            });

            this.lineFeatureCollection().features.forEach((feature) => {
                this.extractLineCoordinates(feature).forEach((coordinate) => coordinates.push(coordinate));
            });

            if (coordinates.length === 0) {
                return;
            }

            const bounds = coordinates.reduce(
                (mapBounds, coordinate) => mapBounds.extend(coordinate),
                new maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
            );

            this.getMap().fitBounds(bounds, {
                padding: this.mapPaddingForOpenPanels(64),
                maxZoom: this.mapUsableMaxZoom,
                duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500,
            });
        },

        fitMapToLine(line) {
            const geometryCoordinates = this.extractLineCoordinates(line.path_geojson);

            if (geometryCoordinates.length > 1) {
                this.fitToCoordinates(geometryCoordinates);
                return;
            }

            const stationCoordinates = this.getLineStationCoordinates(line.id);

            if (import.meta.env.DEV) {
                const stationsForLine = this.getStationsForLine(line.id);

                console.debug('[fotometro] selected line', line.id);
                console.debug('[fotometro] line stations', stationsForLine);
                console.debug('[fotometro] line coordinates', stationCoordinates);
            }

            if (stationCoordinates.length > 0) {
                this.fitToCoordinates(stationCoordinates);
            }
        },

        extractLineCoordinates(pathGeojson) {
            const geometry = pathGeojson?.geometry ?? pathGeojson;
            const coordinates = geometry?.coordinates;

            if (! Array.isArray(coordinates)) {
                return [];
            }

            if (geometry?.type === 'MultiLineString') {
                return coordinates
                    .flat()
                    .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
                    .map((coordinate) => [Number(coordinate[0]), Number(coordinate[1])])
                    .filter(([longitude, latitude]) => Number.isFinite(longitude) && Number.isFinite(latitude));
            }

            return coordinates
                .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
                .map((coordinate) => [Number(coordinate[0]), Number(coordinate[1])])
                .filter(([longitude, latitude]) => Number.isFinite(longitude) && Number.isFinite(latitude));
        },

        getStationsForLine(lineId) {
            const line = this.mapData.lines.find((candidate) => Number(candidate.id) === Number(lineId));

            const topologyStations = this.uniqueTopologyStations(line);

            if (topologyStations.length) {
                return topologyStations;
            }

            if (line?.stations?.length) {
                return this.orderedLineStations(line);
            }

            return this.mapData.stations.filter((station) => this.normalizeLines(station.lines).some(
                (candidate) => Number(candidate.id) === Number(lineId),
            ));
        },

        getLineStationCoordinates(lineId) {
            return this.getStationsForLine(lineId)
                .filter((station) => Number.isFinite(Number(station.longitude)) && Number.isFinite(Number(station.latitude)))
                .map((station) => [
                    Number(station.longitude),
                    Number(station.latitude),
                ]);
        },

        // The line diagram panel floats over the bottom of the map; without
        // this, flyTo/fitBounds centre on the full viewport and the point of
        // interest can end up hidden underneath it.
        mapPaddingForOpenPanels(basePadding = 80) {
            const panel = this.selectedLine ? document.querySelector('.line-diagram-panel') : null;
            const panelHeight = panel ? Math.ceil(panel.getBoundingClientRect().height) : 0;

            return {
                top: basePadding,
                left: basePadding,
                right: basePadding,
                bottom: panelHeight > 0 ? basePadding + panelHeight : basePadding,
            };
        },

        fitToCoordinates(coordinates) {
            if (! this.getMap() || coordinates.length === 0) {
                return;
            }

            const maplibregl = this.getMapLibre();

            this.$nextTick(() => {
                if (coordinates.length === 1) {
                    this.getMap().flyTo({
                        center: coordinates[0],
                        zoom: Math.min(14, this.mapUsableMaxZoom),
                        padding: this.mapPaddingForOpenPanels(40),
                        duration: 700,
                    });

                    return;
                }

                const bounds = coordinates.reduce(
                    (result, coordinate) => result.extend(coordinate),
                    new maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
                );

                this.getMap().fitBounds(bounds, {
                    padding: this.mapPaddingForOpenPanels(80),
                    maxZoom: Math.min(14, this.mapUsableMaxZoom),
                    duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 700,
                });
            });
        },

        queueSearch() {
            this.focusedSearchIndex = -1;
            const query = this.searchQuery.trim();
            clearTimeout(this.searchTimer);

            if (query.length < 2) {
                this.searchRequestController?.abort();
                this.searchRequestSequence++;
                this.searchResults = [];
                this.searchLineResults = [];
                this.searchLoading = false;
                this.searchError = null;
                return;
            }

            this.activePanel = 'search';
            this.searchLoading = true;
            this.searchError = null;
            const requestId = ++this.searchRequestSequence;

            this.searchTimer = setTimeout(() => {
                this.performSearch(query, requestId);
            }, 300);
        },

        searchStations() {
            this.queueSearch();
        },

        performSearch(query = this.searchQuery.trim(), requestId = ++this.searchRequestSequence) {
            try {
                const normalizedQuery = this.normalizeSearchText(query);

                if (normalizedQuery.length < 2) {
                    this.searchResults = [];
                    this.searchLineResults = [];
                    return;
                }

                this.searchLineResults = this.mapData.lines
                    .filter((line) => this.normalizeSearchText(`${line.code} ${line.name} ${line.slug}`).includes(normalizedQuery))
                    .slice(0, 6)
                    .map((line) => ({
                        ...line,
                        station_count: line.station_count ?? this.orderedLineStations(line).length,
                    }));

                this.searchResults = this.mapData.stations
                    .map((station) => ({
                        station,
                        searchable: this.normalizeSearchText([
                            station.name,
                            station.slug,
                            station.city,
                            station.district,
                            ...this.normalizeLines(station.lines).flatMap((line) => [line.code, line.name, line.slug]),
                        ].filter(Boolean).join(' ')),
                    }))
                    .filter(({ searchable }) => searchable.includes(normalizedQuery))
                    .sort((a, b) => {
                        const aStarts = a.searchable.startsWith(normalizedQuery) ? 0 : 1;
                        const bStarts = b.searchable.startsWith(normalizedQuery) ? 0 : 1;

                        return aStarts - bStarts || a.station.name.localeCompare(b.station.name, 'fr');
                    })
                    .slice(0, 12)
                    .map(({ station }) => station);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('[fotometro] search failed', error);
                    this.searchError = error?.status === 429
                        ? 'Recherche temporairement limitee. Reessayez dans un instant.'
                        : 'La recherche est momentanement indisponible.';
                    this.searchResults = [];
                    this.searchLineResults = [];
                }
            } finally {
                if (requestId === this.searchRequestSequence) {
                    this.searchLoading = false;
                }
            }
        },

        normalizeSearchText(value) {
            return String(value ?? '')
                .normalize('NFD')
                .replace(/\p{Diacritic}/gu, '')
                .toLowerCase()
                .trim();
        },

        moveSearchFocus(direction) {
            if (this.searchResults.length === 0) {
                return;
            }

            this.focusedSearchIndex = (this.focusedSearchIndex + direction + this.searchResults.length) % this.searchResults.length;
        },

        chooseFocusedSearchResult() {
            // Enter should pick the top result even if the user never
            // pressed an arrow key to focus one explicitly.
            const index = this.focusedSearchIndex >= 0 ? this.focusedSearchIndex : 0;

            if (this.searchResults[index]) {
                this.selectStation(this.searchResults[index].id, true);
                return;
            }

            if (this.searchLineResults[0]) {
                this.selectSearchLine(this.searchLineResults[0].id);
            }
        },

        closeSearch() {
            clearTimeout(this.searchTimer);
            this.searchRequestController?.abort();
            this.searchRequestSequence++;
            this.searchResults = [];
            this.searchLineResults = [];
            this.searchLoading = false;
            this.searchError = null;
            this.focusedSearchIndex = -1;

            if (this.activePanel === 'search') {
                this.activePanel = null;
            }
        },

        focusSearch() {
            this.$refs.searchInput?.focus();
            this.activePanel = 'search';
        },

        handleEscape() {
            if (this.isBetaNoticeOpen) {
                this.closeBetaNotice();
                return;
            }

            if (this.isAboutOpen) {
                this.closeAbout();
                return;
            }

            if (this.activePanel === 'search') {
                this.closeSearch();
                return;
            }

            if (this.isLinesOpen) {
                this.isLinesOpen = false;
                this.activePanel = null;
                return;
            }

            if (this.isFiltersOpen) {
                this.isFiltersOpen = false;
                this.activePanel = null;
                return;
            }

            if (this.selectedStation) {
                this.clearStationSelection();
            }
        },

        selectSearchLine(lineId) {
            this.closeSearch();
            if (Number(this.selectedLineId) !== Number(lineId)) {
                this.selectLine(Number(lineId));
            }
        },

        openAbout() {
            this.isAboutOpen = true;
            this.activePanel = 'about';
            this.$nextTick(() => this.$refs.aboutClose?.focus());
        },

        closeAbout() {
            this.isAboutOpen = false;
            if (this.activePanel === 'about') {
                this.activePanel = null;
            }
        },

        hasSeenBetaNotice() {
            try {
                return window.localStorage.getItem('fotometro-beta-notice-dismissed') === '1';
            } catch (error) {
                return false;
            }
        },

        closeBetaNotice() {
            this.isBetaNoticeOpen = false;

            try {
                window.localStorage.setItem('fotometro-beta-notice-dismissed', '1');
            } catch (error) {
                // Ignore storage errors (private browsing, quota, etc.) — the
                // notice will just reappear next visit in that case.
            }
        },

        selectStationFromDiagram(stationId) {
            this.selectStation(Number(stationId), true);
        },

        hasLayoutForLine(line) {
            const layout = line?.topology?.layout;

            return Boolean(
                layout
                && Array.isArray(layout.segments)
                && Array.isArray(layout.stations)
            );
        },

        lineDiagramSegments() {
            return Array.isArray(this.selectedLineLayout?.segments)
                ? this.selectedLineLayout.segments.filter((segment) => this.isValidDiagramSegment(segment))
                : [];
        },

        lineDiagramStations() {
            return Array.isArray(this.selectedLineLayout?.stations)
                ? this.selectedLineLayout.stations.filter((station) => this.isValidDiagramStation(station))
                : [];
        },

        isValidDiagramSegment(segment) {
            return Number.isFinite(Number(segment?.x1))
                && Number.isFinite(Number(segment?.y1))
                && Number.isFinite(Number(segment?.x2))
                && Number.isFinite(Number(segment?.y2));
        },

        isValidDiagramStation(station) {
            return Number.isFinite(Number(station?.x))
                && Number.isFinite(Number(station?.y))
                && Number.isFinite(Number(station?.label_x))
                && Number.isFinite(Number(station?.label_y));
        },

        clearRenderedLineDiagram() {
            if (this.$refs.lineDiagramSvgHost) {
                this.$refs.lineDiagramSvgHost.replaceChildren();
            }
        },

        renderSelectedLineDiagram() {
            this.$nextTick(() => {
                const host = this.$refs.lineDiagramSvgHost;

                if (! host) {
                    return;
                }

                host.replaceChildren();

                if (! this.hasSelectedLineLayout) {
                    return;
                }

                const layout = this.selectedLineLayout;
                const svg = this.svgElement('svg', {
                    class: 'line-diagram-svg',
                    role: 'group',
                    'aria-labelledby': 'line-diagram-title',
                    viewBox: layout.view_box?.value || `0 0 ${Number(layout.width) || 1200} ${Number(layout.height) || 360}`,
                    width: Number(layout.width) || 1200,
                    height: Number(layout.height) || 360,
                    'data-layout-type': layout.type || 'unknown',
                });

                const underlay = this.svgElement('g', { class: 'diagram-segments diagram-segments-underlay' });
                this.lineDiagramSegments().forEach((segment) => {
                    underlay.appendChild(this.svgElement('line', {
                        class: 'diagram-segment-underlay',
                        x1: Number(segment.x1),
                        y1: Number(segment.y1),
                        x2: Number(segment.x2),
                        y2: Number(segment.y2),
                    }));
                });
                svg.appendChild(underlay);

                const segments = this.svgElement('g', { class: 'diagram-segments' });
                this.lineDiagramSegments().forEach((segment) => {
                    segments.appendChild(this.svgElement('line', {
                        class: `diagram-segment is-${this.safeSvgClass(segment.kind || 'main')}`,
                        x1: Number(segment.x1),
                        y1: Number(segment.y1),
                        x2: Number(segment.x2),
                        y2: Number(segment.y2),
                    }));
                });
                svg.appendChild(segments);

                const stations = this.svgElement('g', { class: 'diagram-stations' });
                this.lineDiagramStations().forEach((station) => {
                    stations.appendChild(this.renderDiagramStation(station));
                });
                svg.appendChild(stations);
                host.appendChild(svg);
                this.fixTerminusBoxWidths(svg);
                this.bindDiagramScrollInteractions();
                this.sizeDiagramPanelToContent(host);
                this.logDiagramScrollDiagnostic(layout, svg, host);
                this.queueRealDiagramDebug();
                this.scrollDiagramToStation(this.selectedStationId);
            });
        },

        // Line diagrams vary hugely in width (line 7bis has 8 stations,
        // line 13 has 32), so a fixed-width drawer is either too cramped or
        // wastes horizontal space that could show the map instead. Desktop
        // only: mobile always uses the full-width sheet from CSS.
        sizeDiagramPanelToContent(host) {
            const panel = document.querySelector('.line-diagram-panel');

            if (! panel || window.innerWidth < 768) {
                if (panel) {
                    panel.style.removeProperty('width');
                }

                return;
            }

            const contentWidth = host.scrollWidth;
            const chrome = 40; // panel padding + a small safety margin
            // The status legend ("Non commencée" ... "Sélectionnée") needs
            // about 630px to stay on one line; never go narrower than that,
            // even for very short diagrams (e.g. line 3B's 4 stations).
            const minWidth = 640;
            const maxWidth = window.innerWidth - 48;
            const width = Math.min(maxWidth, Math.max(minWidth, contentWidth + chrome));

            panel.style.width = `${Math.round(width)}px`;
        },

        // The PHP layout estimates each label's width from its character
        // count to size things before anything is rendered, but that's only
        // ever an approximation of the real font metrics - once the SVG is
        // in the DOM, shrink each terminus cartouche to the label's actual
        // rendered width so no extra blue trails past the last letter.
        fixTerminusBoxWidths(svg) {
            svg.querySelectorAll('.diagram-svg-station.is-terminus').forEach((stationGroup) => {
                const text = stationGroup.querySelector('text.diagram-svg-label');
                const box = stationGroup.querySelector('rect.diagram-svg-terminus-box');

                if (! text || ! box) {
                    return;
                }

                const measuredWidth = Math.max(0, ...[...text.querySelectorAll('tspan')].map((tspan) => {
                    try {
                        return tspan.getComputedTextLength();
                    } catch (error) {
                        return 0;
                    }
                }));

                if (! Number.isFinite(measuredWidth) || measuredWidth <= 0) {
                    return;
                }

                const labelX = Number(text.getAttribute('x'));
                box.setAttribute('x', String(labelX - 4));
                box.setAttribute('width', String(measuredWidth + 8));
            });
        },

        bindDiagramScrollInteractions() {
            const scroller = this.$refs.lineDiagramScroll;

            if (! scroller || scroller.dataset.scrollBound) {
                return;
            }

            scroller.dataset.scrollBound = 'true';

            // The diagram is primarily a horizontal timeline: a plain vertical
            // mouse-wheel scroll (no shift, no dominant deltaX from a trackpad
            // swipe) is redirected to horizontal scrolling, since that's the
            // only way most mouse users can move along the line at all.
            scroller.addEventListener('wheel', (event) => {
                const canScrollHorizontally = scroller.scrollWidth > scroller.clientWidth;

                if (! canScrollHorizontally || event.shiftKey || Math.abs(event.deltaX) > Math.abs(event.deltaY)) {
                    return;
                }

                event.preventDefault();
                scroller.scrollLeft += event.deltaY;
            }, { passive: false });

            // Click-and-drag panning ("grab to scroll"), since the diagram can
            // be wider than the panel and not everyone scrolls with a wheel.
            let isPanning = false;
            let dragged = false;
            let startClientX = 0;
            let startScrollLeft = 0;

            scroller.addEventListener('pointerdown', (event) => {
                if (event.button !== 0 || event.target.closest('.diagram-svg-station')) {
                    return;
                }

                isPanning = true;
                dragged = false;
                startClientX = event.clientX;
                startScrollLeft = scroller.scrollLeft;
                scroller.setPointerCapture(event.pointerId);
                scroller.classList.add('is-panning');
            });

            scroller.addEventListener('pointermove', (event) => {
                if (! isPanning) {
                    return;
                }

                const delta = event.clientX - startClientX;

                if (Math.abs(delta) > 3) {
                    dragged = true;
                }

                scroller.scrollLeft = startScrollLeft - delta;
            });

            const endPan = (event) => {
                if (! isPanning) {
                    return;
                }

                isPanning = false;
                scroller.classList.remove('is-panning');

                if (event && scroller.hasPointerCapture(event.pointerId)) {
                    scroller.releasePointerCapture(event.pointerId);
                }
            };

            scroller.addEventListener('pointerup', endPan);
            scroller.addEventListener('pointercancel', endPan);

            // A drag that moved the scroll position shouldn't also register as
            // a click on whatever station happens to be under the pointer.
            scroller.addEventListener('click', (event) => {
                if (dragged) {
                    event.stopPropagation();
                    event.preventDefault();
                }
            }, true);
        },

        logDiagramScrollDiagnostic(layout, svg, host) {
            if (! (this.isLocal || import.meta.env.DEV)) {
                return;
            }

            this.$nextTick(() => {
                const scroller = this.$refs.lineDiagramScroll;
                const svgStyle = svg ? getComputedStyle(svg) : null;
                const hostStyle = host ? getComputedStyle(host) : null;

                console.debug('[fotometro] diagram scroll', {
                    clientWidth: scroller?.clientWidth,
                    scrollWidth: scroller?.scrollWidth,
                    scrollable: Boolean(scroller && scroller.scrollWidth > scroller.clientWidth),
                    layoutWidth: Number(layout?.width) || null,
                    svgWidth: svg?.getAttribute('width') ?? null,
                    svgComputedWidth: svgStyle?.width ?? null,
                    hostComputedWidth: hostStyle?.width ?? null,
                });
            });
        },

        queueRealDiagramDebug() {
            if (! (this.isLocal || import.meta.env.DEV)) {
                return;
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => this.debugRealDiagram());
            });
        },

        debugRealDiagram() {
            const panel = document.querySelector('.line-diagram-panel');
            const scroller = this.$refs.lineDiagramScroll;
            const host = this.$refs.lineDiagramSvgHost;
            const svg = host?.querySelector('.line-diagram-svg') ?? null;
            const rect = (element) => {
                if (! element) {
                    return null;
                }

                const bounds = element.getBoundingClientRect();

                return {
                    left: bounds.left,
                    top: bounds.top,
                    right: bounds.right,
                    bottom: bounds.bottom,
                    width: bounds.width,
                    height: bounds.height,
                };
            };
            const className = (element) => String(element?.className?.baseVal ?? element?.className ?? '');
            const describe = (element) => {
                if (! element) {
                    return null;
                }

                const styles = getComputedStyle(element);

                return {
                    tag: element.tagName?.toLowerCase(),
                    className: className(element),
                    rect: rect(element),
                    clientWidth: element.clientWidth ?? null,
                    scrollWidth: element.scrollWidth ?? null,
                    clientHeight: element.clientHeight ?? null,
                    scrollHeight: element.scrollHeight ?? null,
                    position: styles.position,
                    display: styles.display,
                    overflow: styles.overflow,
                    overflowX: styles.overflowX,
                    width: styles.width,
                    minWidth: styles.minWidth,
                    maxWidth: styles.maxWidth,
                    flex: styles.flex,
                    transform: styles.transform,
                    pointerEvents: styles.pointerEvents,
                };
            };
            const parents = [];
            let parent = svg?.parentElement ?? host?.parentElement ?? scroller?.parentElement ?? panel?.parentElement ?? null;

            while (parent) {
                parents.push(describe(parent));

                if (parent === document.body) {
                    break;
                }

                parent = parent.parentElement;
            }

            const panelRect = rect(panel);
            const sampleX = panelRect ? panelRect.left + Math.min(panelRect.width / 2, panelRect.width - 10) : window.innerWidth / 2;
            const sampleY = panelRect ? panelRect.top + Math.min(panelRect.height / 2, panelRect.height - 10) : window.innerHeight / 2;
            const elementAtPoint = document.elementFromPoint(sampleX, sampleY);

            console.group('[fotometro] real diagram diagnostic');
            console.table({
                panel: describe(panel),
                scroll: describe(scroller),
                host: describe(host),
                svg: {
                    ...describe(svg),
                    attrWidth: svg?.getAttribute('width') ?? null,
                    attrHeight: svg?.getAttribute('height') ?? null,
                    viewBox: svg?.getAttribute('viewBox') ?? null,
                },
            });
            console.log('parents', parents);
            console.log('elementFromPoint', {
                x: sampleX,
                y: sampleY,
                tag: elementAtPoint?.tagName?.toLowerCase() ?? null,
                className: className(elementAtPoint),
            });
            console.log('scrollable', Boolean(scroller && scroller.scrollWidth > scroller.clientWidth));
            console.groupEnd();
        },

        renderDiagramStation(station) {
            const group = this.svgElement('g', {
                class: [
                    'diagram-svg-station',
                    Number(this.selectedStationId) === Number(station.id) ? 'is-selected' : '',
                    station.is_terminus ? 'is-terminus' : '',
                ].filter(Boolean).join(' '),
                'data-station-id': station.id,
                role: 'button',
                tabindex: '0',
                'aria-label': `Sélectionner ${station.name || 'station'}`,
            });

            group.addEventListener('click', () => this.selectStationFromDiagram(station.id));
            group.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    this.selectStationFromDiagram(station.id);
                }
            });

            group.appendChild(this.svgElement('circle', {
                class: `diagram-svg-node ${this.coverageSvgNodeClass(station)}`,
                cx: Number(station.x),
                cy: Number(station.y),
                r: 7,
            }));

            if (Number(this.selectedStationId) === Number(station.id)) {
                group.appendChild(this.svgElement('circle', {
                    class: 'diagram-svg-selected-ring',
                    cx: Number(station.x),
                    cy: Number(station.y),
                    r: 13,
                }));
            }

            const labelGroup = this.svgElement('g', {
                transform: `rotate(${Number(station.label_rotation) || 0} ${Number(station.label_x)} ${Number(station.label_y)})`,
            });

            if (station.is_terminus && this.isValidTerminusBox(station.terminus_label_box)) {
                labelGroup.appendChild(this.svgElement('rect', {
                    class: 'diagram-svg-terminus-box',
                    x: Number(station.terminus_label_box.x),
                    y: Number(station.terminus_label_box.y),
                    width: Number(station.terminus_label_box.width),
                    height: Number(station.terminus_label_box.height),
                    rx: Number(station.terminus_label_box.rx) || 0,
                }));
            }

            const label = this.svgElement('text', {
                class: `diagram-svg-label${station.is_terminus ? ' is-terminus' : ''}`,
                x: Number(station.label_x),
                y: Number(station.label_y),
                'text-anchor': station.label_anchor || 'start',
            });
            const labelLines = Array.isArray(station.label_lines) && station.label_lines.length
                ? station.label_lines
                : [station.name || ''];
            labelLines.forEach((line, index) => {
                const tspan = this.svgElement('tspan', {
                    x: Number(station.label_x),
                    dy: index === 0 ? 0 : '1.05em',
                });
                tspan.textContent = line;
                label.appendChild(tspan);
            });
            labelGroup.appendChild(label);
            group.appendChild(labelGroup);

            if (this.showConnections) {
                const connections = this.svgElement('g', { class: 'diagram-svg-connections' });
                (station.connection_badges ?? []).forEach((connection) => {
                    if (! Number.isFinite(Number(connection?.x)) || ! Number.isFinite(Number(connection?.y))) {
                        return;
                    }

                    const connectionGroup = this.svgElement('g');
                    connectionGroup.appendChild(this.svgElement('circle', {
                        class: 'diagram-svg-connection-circle',
                        cx: Number(connection.x),
                        cy: Number(connection.y),
                        r: 8,
                        style: `fill:${this.safeLineColor(connection.color)}`,
                    }));

                    const text = this.svgElement('text', {
                        class: 'diagram-svg-connection-text',
                        x: Number(connection.x),
                        y: Number(connection.y) + 3,
                        'text-anchor': 'middle',
                        style: `fill:${this.safeLineColor(connection.text_color)}`,
                    });
                    text.textContent = connection.code || '';
                    connectionGroup.appendChild(text);
                    connections.appendChild(connectionGroup);
                });
                group.appendChild(connections);
            }

            return group;
        },

        svgElement(name, attributes = {}) {
            const element = document.createElementNS('http://www.w3.org/2000/svg', name);

            Object.entries(attributes).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') {
                    return;
                }

                element.setAttribute(key, String(value));
            });

            return element;
        },

        isValidTerminusBox(box) {
            return Number.isFinite(Number(box?.x))
                && Number.isFinite(Number(box?.y))
                && Number.isFinite(Number(box?.width))
                && Number.isFinite(Number(box?.height));
        },

        safeSvgClass(value) {
            return String(value || 'main').replace(/[^a-zA-Z0-9_-]/g, '-');
        },

        scrollDiagramToStation(stationId) {
            requestAnimationFrame(() => {
                const safeId = Number(stationId);

                if (! Number.isInteger(safeId)) {
                    return;
                }

                const selector = `[data-station-id="${safeId}"]`;
                const scroller = this.$refs.lineDiagramScroll;
                scroller?.querySelectorAll('.is-active-occurrence').forEach((element) => element.classList.remove('is-active-occurrence'));
                this.$refs.lineDiagramMobileScroller?.querySelectorAll('.is-active-occurrence').forEach((element) => element.classList.remove('is-active-occurrence'));
                scroller?.querySelectorAll(selector).forEach((element, index) => {
                    element.classList.toggle('is-active-occurrence', true);

                    if (index === 0) {
                        const station = this.lineDiagramStations().find((candidate) => Number(candidate.id) === safeId);
                        const left = (Number(station?.x) || 0) - (scroller.clientWidth / 2);

                        scroller.scrollTo({
                            left: Math.max(0, left),
                            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                        });
                    }
                });
                this.$refs.lineDiagramMobileScroller?.querySelectorAll(selector).forEach((element, index) => {
                    element.classList.toggle('is-active-occurrence', true);

                    if (index === 0) {
                        element.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    }
                });
            });
        },

        orderedLineStations(line) {
            return [...(line?.stations || [])].sort((a, b) => Number(a.position || 0) - Number(b.position || 0));
        },

        topologyBranches(line) {
            const branches = line?.topology?.branches;

            if (Array.isArray(branches) && branches.length > 0) {
                return branches.map((branch) => ({
                    ...branch,
                    stations: [...(branch.stations || [])].sort((a, b) => Number(a.position || 0) - Number(b.position || 0)),
                }));
            }

            return [{
                key: 'main',
                label: 'Sequence principale',
                stations: this.orderedLineStations(line),
            }];
        },

        uniqueTopologyStations(line) {
            const stations = [];
            const seen = new Set();

            this.topologyBranches(line).forEach((branch) => {
                branch.stations.forEach((station) => {
                    if (! seen.has(Number(station.id))) {
                        seen.add(Number(station.id));
                        stations.push(station);
                    }
                });
            });

            return stations;
        },

        lineTerminusLabel(line) {
            const orientation = line?.topology?.orientation;

            if (orientation?.start && Array.isArray(orientation.ends) && orientation.ends.length > 0) {
                return `${orientation.start.name} → ${orientation.ends.map((station) => station.name).join(' / ')}`;
            }

            const termini = this.uniqueTopologyStations(line).filter((station) => station.is_terminus);

            if (termini.length >= 2) {
                return `${termini[0].name} → ${termini[termini.length - 1].name}`;
            }

            if (termini.length === 1) {
                return `Terminus : ${termini[0].name}`;
            }

            const stations = this.uniqueTopologyStations(line);

            if (stations.length >= 2) {
                return `${stations[0].name} → ${stations[stations.length - 1].name}`;
            }

            return 'Terminus à compléter';
        },

        coverageNodeClass(station) {
            return {
                [`status-${station.coverage_status?.value || 'not_started'}`]: true,
                'is-selected': Number(this.selectedStationId) === Number(station.id),
                'is-terminus': Boolean(station.is_terminus),
            };
        },

        coverageSvgNodeClass(station) {
            return `status-${station.coverage_status?.value || 'not_started'}`;
        },

        safeLineColor(value) {
            return /^#[0-9a-fA-F]{3,8}$/.test(value || '') ? value : '#151515';
        },

        normalizeLines(lines) {
            if (Array.isArray(lines)) {
                return lines;
            }

            if (lines && typeof lines === 'object') {
                return Object.values(lines);
            }

            return [];
        },

        destroy() {
            const container = document.getElementById('metro-map');

            if (container?.__fotometroMapInstance === mapInstance) {
                delete container.__fotometroMapInstance;
            }

            mapInstance?.remove();
            mapInstance = null;
            maplibreglInstance = null;
        },

        reportFatalMapError(message, error) {
            const details = describeMapError(error);
            this.mapFatalError = `${message}: ${details.name}: ${details.message}`;

            if (this.isLocal) {
                console.error(`[fotometro] ${message}`, details);
            }
        },
    };
};

function buildAdminMapConfig(input = {}) {
    const basemapDriver = normalizeBasemapDriver(input.basemapDriver || 'raster');
    const maxZoom = input.maxZoom !== null && input.maxZoom !== undefined && input.maxZoom !== ''
        ? Number(input.maxZoom)
        : 19;

    return {
        basemapDriver,
        styleUrl: input.styleUrl || '',
        rasterUrl: input.rasterUrl || '',
        rasterTileSize: Number(input.rasterTileSize || 256),
        attribution: input.attribution || '',
        centerLongitude: Number(input.centerLongitude || 2.3522),
        centerLatitude: Number(input.centerLatitude || 48.8566),
        zoom: Number(input.zoom || 11.5),
        maxZoom,
        hasBasemapConfig: basemapDriver === 'raster' ? Boolean(input.rasterUrl) : Boolean(input.styleUrl),
    };
}

window.fotometroPhotoForm = function fotometroPhotoForm(options) {
    return {
        lineId: options.initialLineId ? String(options.initialLineId) : '',
        stationId: options.initialStationId ? String(options.initialStationId) : '',
        accessId: options.initialAccessId ? String(options.initialAccessId) : '',
        stations: [],
        accesses: [],
        loadingStations: false,
        loadingAccesses: false,
        map: null,
        maplibregl: null,
        markers: [],
        mapStatus: 'Sélectionnez une station.',
        mapConfig: buildAdminMapConfig(options.mapConfig || {}),
        lineStationsUrl: options.lineStationsUrl,
        stationAccessesUrl: options.stationAccessesUrl,

        async init() {
            if (this.lineId) {
                await this.loadStations(true);
            }

            if (this.stationId) {
                await this.loadAccesses(true);
                await this.refreshMap();
            }
        },

        async lineChanged() {
            this.stationId = '';
            this.accessId = '';
            this.stations = [];
            this.accesses = [];
            this.clearMarkers();
            this.mapStatus = this.lineId ? 'Chargement des stations...' : 'Sélectionnez une station.';

            if (this.lineId) {
                await this.loadStations(false);
            }
        },

        async stationChanged() {
            this.accessId = '';
            this.accesses = [];
            await this.loadAccesses(false);
            await this.refreshMap();
        },

        accessChanged() {
            this.refreshMap();
        },

        async loadStations(keepSelection) {
            this.loadingStations = true;

            try {
                const response = await fetch(this.lineStationsUrl.replace('__LINE__', encodeURIComponent(this.lineId)), {
                    headers: { Accept: 'application/json' },
                });

                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                this.stations = Array.isArray(payload.data) ? payload.data : [];

                if (! keepSelection || ! this.stations.some((station) => String(station.id) === String(this.stationId))) {
                    this.stationId = '';
                    this.accessId = '';
                    this.accesses = [];
                } else {
                    // The <select> renders its <option>s from `stations` via x-for; x-model only
                    // re-applies the DOM value when `stationId` itself changes, not when the options
                    // it depends on appear. Without this, a preselected station with a value set
                    // before its <option> existed shows blank even though the model is correct.
                    const current = this.stationId;
                    this.stationId = '';
                    await this.$nextTick();
                    this.stationId = current;
                }

                this.mapStatus = this.stationId ? 'Station sélectionnée.' : 'Sélectionnez une station.';
            } catch (error) {
                this.mapStatus = 'Impossible de charger les stations.';
                console.error('[fotometro] admin station loading failed', error);
            } finally {
                this.loadingStations = false;
            }
        },

        async loadAccesses(keepSelection) {
            if (! this.stationId) {
                this.accesses = [];
                return;
            }

            this.loadingAccesses = true;

            try {
                const response = await fetch(this.stationAccessesUrl.replace('__STATION__', encodeURIComponent(this.stationId)), {
                    headers: { Accept: 'application/json' },
                });

                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                this.accesses = Array.isArray(payload.data) ? payload.data : [];

                if (! keepSelection || ! this.accesses.some((access) => String(access.id) === String(this.accessId))) {
                    this.accessId = '';
                } else {
                    // Same x-model/x-for race as loadStations() above.
                    const current = this.accessId;
                    this.accessId = '';
                    await this.$nextTick();
                    this.accessId = current;
                }
            } catch (error) {
                this.accesses = [];
                this.mapStatus = 'Impossible de charger les accès.';
                console.error('[fotometro] admin access loading failed', error);
            } finally {
                this.loadingAccesses = false;
            }
        },

        async ensureMap() {
            if (! this.$refs.map) {
                return false;
            }

            if (! this.mapConfig.hasBasemapConfig) {
                this.$refs.map.innerHTML = `<div class="grid h-full place-items-center p-6 text-center text-sm text-black/60">${basemapConfigurationMessage(this.mapConfig)}</div>`;
                return false;
            }

            this.maplibregl ??= await loadMapLibre();

            if (this.map) {
                return true;
            }

            try {
                this.map = new this.maplibregl.Map({
                    container: this.$refs.map,
                    style: resolveMapStyle(this.mapConfig),
                    center: [this.mapConfig.centerLongitude, this.mapConfig.centerLatitude],
                    zoom: Math.min(13, this.mapConfig.maxZoom),
                    maxZoom: this.mapConfig.maxZoom,
                    attributionControl: false,
                });

                this.map.addControl(new this.maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');

                if (this.mapConfig.attribution) {
                    this.map.addControl(new this.maplibregl.AttributionControl({
                        customAttribution: this.mapConfig.attribution,
                        compact: true,
                    }));
                }

                this.map.on('load', () => {
                    this.map.resize();
                });

                return true;
            } catch (error) {
                this.mapStatus = `Erreur MapLibre : ${error.message || error}`;
                console.error('[fotometro] admin map initialization failed', error);
                return false;
            }
        },

        async refreshMap() {
            const station = this.selectedStation();

            if (! station) {
                this.mapStatus = 'Sélectionnez une station.';
                return;
            }

            if (! await this.ensureMap()) {
                return;
            }

            this.clearMarkers();

            const coordinates = [];
            const stationCoordinate = this.coordinateFor(station);

            if (stationCoordinate) {
                coordinates.push(stationCoordinate);
                this.addMarker(stationCoordinate, station.name, 'station', false, null);
            }

            let geolocatedAccessCount = 0;

            this.accesses.forEach((access) => {
                const coordinate = this.coordinateFor(access);

                if (coordinate) {
                    geolocatedAccessCount++;
                    coordinates.push(coordinate);
                    this.addMarker(coordinate, access.name, 'access', String(access.id) === String(this.accessId), access.id);
                }
            });

            if (coordinates.length === 0) {
                this.mapStatus = 'Aucune coordonnée disponible pour cette sélection.';
                return;
            }

            if (coordinates.length === 1) {
                this.map.flyTo({ center: coordinates[0], zoom: Math.min(15, this.mapConfig.maxZoom), duration: 200 });
            } else {
                const bounds = coordinates.reduce(
                    (result, coordinate) => result.extend(coordinate),
                    new this.maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
                );

                this.map.fitBounds(bounds, { padding: 64, maxZoom: Math.min(16, this.mapConfig.maxZoom), duration: 200 });
            }

            this.map.resize();
            this.mapStatus = geolocatedAccessCount > 0
                ? `${geolocatedAccessCount} accès géolocalisé(s).`
                : 'Aucun accès géolocalisé pour cette station.';
        },

        addMarker(coordinate, label, type, selected, id) {
            const marker = document.createElement('button');
            marker.type = 'button';
            marker.className = type === 'station'
                ? 'h-5 w-5 rounded-full border-2 border-white bg-black shadow'
                : 'h-4 w-4 rounded-full border-2 border-white bg-blue-700 shadow';
            marker.classList.toggle('ring-4', selected);
            marker.classList.toggle('ring-blue-300', selected);
            marker.setAttribute('aria-label', label || 'Repère');

            const mapMarker = new this.maplibregl.Marker({ element: marker })
                .setLngLat(coordinate)
                .addTo(this.map);

            if (type === 'access') {
                marker.addEventListener('click', () => {
                    this.accessId = id ? String(id) : this.accessId;
                    this.refreshMap();
                });
            }

            this.markers.push(mapMarker);
        },

        clearMarkers() {
            this.markers.forEach((marker) => marker.remove());
            this.markers = [];
        },

        selectedStation() {
            return this.stations.find((station) => String(station.id) === String(this.stationId)) || null;
        },

        coordinateFor(item) {
            const longitude = Number(item.longitude);
            const latitude = Number(item.latitude);

            if (! Number.isFinite(longitude) || ! Number.isFinite(latitude)) {
                return null;
            }

            return [longitude, latitude];
        },

        destroy() {
            this.clearMarkers();
            this.map?.remove();
            this.map = null;
        },
    };
};

window.fotometroPhotoImportWizard = function fotometroPhotoImportWizard(options) {
    return {
        step: 'drop',
        photos: [],
        stationsByLine: {},
        accessesByStation: {},
        lineStationsUrl: options.lineStationsUrl,
        stationAccessesUrl: options.stationAccessesUrl,
        detectStationUrl: options.detectStationUrl,
        submitError: '',
        nextId: 1,

        filesAdded(fileList) {
            Array.from(fileList || []).forEach((file) => this.addPhoto(file));

            if (this.photos.length > 0) {
                this.step = 'review';
            }
        },

        addPhoto(file) {
            const photo = {
                id: this.nextId++,
                file,
                previewUrl: URL.createObjectURL(file),
                lineId: '',
                stationId: '',
                accessId: '',
                stations: [],
                accesses: [],
                loadingStations: false,
                loadingAccesses: false,
                categoryId: '',
                description: '',
                detectionStatus: 'Détection de la station en cours...',
            };

            this.photos.push(photo);
            // Re-read the pushed entry so mutations flow through Alpine's reactive
            // proxy (the raw `photo` object above bypasses it and never updates the DOM).
            this.detectStationFor(this.photos[this.photos.length - 1]);
        },

        removePhoto(index) {
            URL.revokeObjectURL(this.photos[index].previewUrl);
            this.photos.splice(index, 1);

            if (this.photos.length === 0) {
                this.step = 'drop';
            }
        },

        scrollToPhoto(id) {
            document.getElementById(`photo-row-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        async detectStationFor(photo) {
            try {
                const body = new FormData();
                body.append('file', photo.file);

                const response = await fetch(this.detectStationUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body,
                });

                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();

                if (! payload.matched) {
                    photo.detectionStatus = 'Aucune donnée de localisation exploitable, sélectionnez la station manuellement.';
                    return;
                }

                photo.lineId = String(payload.line.id);
                await this.loadStationsFor(photo, false);
                photo.stationId = String(payload.station.id);
                await this.loadAccessesFor(photo);
                photo.detectionStatus = `Station détectée : ${payload.station.name} (≈ ${Math.round(payload.distance_meters)} m) — vérifiez avant de valider.`;
            } catch (error) {
                console.error('[fotometro] station detection failed', error);
                photo.detectionStatus = 'Aucune donnée de localisation exploitable, sélectionnez la station manuellement.';
            }
        },

        async lineChangedFor(photo) {
            photo.stationId = '';
            photo.accessId = '';
            photo.stations = [];
            photo.accesses = [];

            if (photo.lineId) {
                await this.loadStationsFor(photo, false);
            }
        },

        async stationChangedFor(photo) {
            photo.accessId = '';
            photo.accesses = [];
            await this.loadAccessesFor(photo);
        },

        async loadStationsFor(photo, keepSelection) {
            if (! photo.lineId) {
                photo.stations = [];
                return;
            }

            if (this.stationsByLine[photo.lineId]) {
                photo.stations = this.stationsByLine[photo.lineId];
            } else {
                photo.loadingStations = true;

                try {
                    const response = await fetch(this.lineStationsUrl.replace('__LINE__', encodeURIComponent(photo.lineId)), {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    const stations = Array.isArray(payload.data) ? payload.data : [];
                    this.stationsByLine[photo.lineId] = stations;
                    photo.stations = stations;
                } catch (error) {
                    console.error('[fotometro] station list loading failed', error);
                    photo.stations = [];
                } finally {
                    photo.loadingStations = false;
                }
            }

            if (! keepSelection || ! photo.stations.some((station) => String(station.id) === String(photo.stationId))) {
                photo.stationId = '';
                photo.accessId = '';
                photo.accesses = [];
            }
        },

        async loadAccessesFor(photo) {
            if (! photo.stationId) {
                photo.accesses = [];
                return;
            }

            if (this.accessesByStation[photo.stationId]) {
                photo.accesses = this.accessesByStation[photo.stationId];
                return;
            }

            photo.loadingAccesses = true;

            try {
                const response = await fetch(this.stationAccessesUrl.replace('__STATION__', encodeURIComponent(photo.stationId)), {
                    headers: { Accept: 'application/json' },
                });

                if (! response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const accesses = Array.isArray(payload.data) ? payload.data : [];
                this.accessesByStation[photo.stationId] = accesses;
                photo.accesses = accesses;
            } catch (error) {
                console.error('[fotometro] access list loading failed', error);
                photo.accesses = [];
            } finally {
                photo.loadingAccesses = false;
            }
        },

        duplicateLocation(sourceIndex) {
            const source = this.photos[sourceIndex];

            this.photos.forEach((photo, index) => {
                if (index === sourceIndex) {
                    return;
                }

                photo.lineId = source.lineId;
                photo.stationId = source.stationId;
                photo.accessId = source.accessId;
                photo.stations = source.stations;
                photo.accesses = source.accesses;
            });
        },

        duplicateField(sourceIndex, field) {
            const value = this.photos[sourceIndex][field];

            this.photos.forEach((photo, index) => {
                if (index !== sourceIndex) {
                    photo[field] = value;
                }
            });
        },

        handleSubmit(event) {
            if (this.photos.length === 0 || this.photos.some((photo) => ! photo.stationId)) {
                event.preventDefault();
                this.submitError = 'Chaque photo doit avoir une station sélectionnée.';
                return;
            }

            this.submitError = '';

            const dataTransfer = new DataTransfer();
            this.photos.forEach((photo) => dataTransfer.items.add(photo.file));
            this.$refs.filesInput.files = dataTransfer.files;
        },
    };
};

window.fotometroStationAccessMap = function fotometroStationAccessMap(options) {
    return {
        map: null,
        maplibregl: null,
        markers: [],
        selectedAccessId: options.selectedAccessId ? String(options.selectedAccessId) : '',
        mapConfig: buildAdminMapConfig(options.mapConfig || {}),
        payload: options.payload || { station: null, accesses: [] },
        mapStatus: 'Carte des accès.',

        async init() {
            await this.refreshMap();
        },

        async ensureMap() {
            if (! this.$refs.map || ! this.mapConfig.hasBasemapConfig) {
                return false;
            }

            this.maplibregl ??= await loadMapLibre();

            if (this.map) {
                return true;
            }

            this.map = new this.maplibregl.Map({
                container: this.$refs.map,
                style: resolveMapStyle(this.mapConfig),
                center: this.stationCoordinate() || [this.mapConfig.centerLongitude, this.mapConfig.centerLatitude],
                zoom: Math.min(14, this.mapConfig.maxZoom),
                maxZoom: this.mapConfig.maxZoom,
                attributionControl: false,
            });

            this.map.addControl(new this.maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');

            if (this.mapConfig.attribution) {
                this.map.addControl(new this.maplibregl.AttributionControl({
                    customAttribution: this.mapConfig.attribution,
                    compact: true,
                }));
            }

            return true;
        },

        async refreshMap() {
            if (! await this.ensureMap()) {
                this.mapStatus = 'Configuration cartographique indisponible.';
                return;
            }

            this.clearMarkers();
            const coordinates = [];
            const stationCoordinate = this.stationCoordinate();

            if (stationCoordinate) {
                coordinates.push(stationCoordinate);
                this.addMarker(stationCoordinate, this.payload.station.name, 'station', false, null, false, this.payload.station.status_color);
            }

            this.geolocatedAccesses().forEach((access) => {
                const coordinate = [Number(access.longitude), Number(access.latitude)];
                coordinates.push(coordinate);
                this.addMarker(coordinate, access.name, 'access', String(access.id) === String(this.selectedAccessId), access.id, access.photo_count > 0, null, access.number);
            });

            if (coordinates.length === 0) {
                this.mapStatus = 'Aucune coordonnée disponible.';
                return;
            }

            if (coordinates.length === 1) {
                this.map.flyTo({ center: coordinates[0], zoom: Math.min(15, this.mapConfig.maxZoom), duration: 0 });
            } else {
                const bounds = coordinates.reduce(
                    (result, coordinate) => result.extend(coordinate),
                    new this.maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
                );

                this.map.fitBounds(bounds, { padding: 56, maxZoom: Math.min(16, this.mapConfig.maxZoom), duration: 0 });
            }

            this.map.resize();
            this.mapStatus = this.geolocatedAccesses().length > 0
                ? `${this.geolocatedAccesses().length} accès géolocalisé(s).`
                : 'Aucun accès géolocalisé pour cette station.';
        },

        selectAccess(accessId) {
            this.selectedAccessId = String(accessId);
            this.refreshMap();

            const access = this.geolocatedAccesses().find((candidate) => String(candidate.id) === String(accessId));
            if (access && this.map) {
                this.map.flyTo({
                    center: [Number(access.longitude), Number(access.latitude)],
                    zoom: Math.min(17, this.mapConfig.maxZoom),
                    duration: 250,
                });
            }

            if (window.Livewire) {
                window.Livewire.dispatch('filterByAccess', { accessId: Number(accessId) });
            }
        },

        addMarker(coordinate, label, type, selected, id, hasPhotos = false, statusColor = null, number = null) {
            const marker = document.createElement('button');
            marker.type = 'button';
            const numbered = type === 'access' && number;
            marker.className = type === 'station'
                ? 'h-5 w-5 rounded-full border-2 border-white bg-black shadow'
                : numbered
                    ? 'flex h-6 w-6 items-center justify-center rounded-full border-2 border-white shadow text-[11px] font-bold leading-none text-white'
                    : 'h-4 w-4 rounded-full border-2 border-white shadow';
            marker.style.backgroundColor = type === 'station' ? (statusColor || '#151515') : (hasPhotos ? '#166534' : '#1d4ed8');
            marker.classList.toggle('ring-4', selected);
            marker.classList.toggle('ring-amber-300', selected);
            marker.setAttribute('aria-label', label || 'Repère');

            if (numbered) {
                marker.textContent = String(number);
            }

            if (id) {
                marker.addEventListener('click', () => this.selectAccess(id));
            }

            this.markers.push(new this.maplibregl.Marker({ element: marker }).setLngLat(coordinate).addTo(this.map));
        },

        stationCoordinate() {
            const station = this.payload.station;
            const longitude = Number(station?.longitude);
            const latitude = Number(station?.latitude);

            return Number.isFinite(longitude) && Number.isFinite(latitude) ? [longitude, latitude] : null;
        },

        geolocatedAccesses() {
            return (this.payload.accesses || []).filter((access) =>
                Number.isFinite(Number(access.longitude)) && Number.isFinite(Number(access.latitude)),
            );
        },

        clearMarkers() {
            this.markers.forEach((marker) => marker.remove());
            this.markers = [];
        },

        destroy() {
            this.clearMarkers();
            this.map?.remove();
            this.map = null;
        },
    };
};

window.fotometroLightbox = function fotometroLightbox() {
    return {
        open: false,
        photo: null,
        index: -1,
        total: 0,

        triggers() {
            return Array.from(this.$root.querySelectorAll('[data-lightbox]'));
        },

        photoFromTrigger(trigger) {
            return {
                image: trigger.dataset.lightboxImage || '',
                title: trigger.dataset.lightboxTitle || '',
                description: trigger.dataset.lightboxDescription || '',
                category: trigger.dataset.lightboxCategory || '',
                copyright: trigger.dataset.lightboxCopyright || '',
                credit: trigger.dataset.lightboxCredit || '',
                license: trigger.dataset.lightboxLicense || '',
                takenAt: trigger.dataset.lightboxTakenAt || '',
                url: trigger.dataset.lightboxUrl || trigger.href,
            };
        },

        handleClick(event) {
            const trigger = event.target.closest('[data-lightbox]');

            if (! trigger) {
                return;
            }

            event.preventDefault();

            const list = this.triggers();
            this.index = list.indexOf(trigger);
            this.total = list.length;
            this.photo = this.photoFromTrigger(trigger);
            this.open = true;
            document.body.classList.add('overflow-hidden');
        },

        next() {
            const list = this.triggers();

            if (list.length === 0 || this.index === -1) {
                return;
            }

            this.index = (this.index + 1) % list.length;
            this.total = list.length;
            this.photo = this.photoFromTrigger(list[this.index]);
        },

        prev() {
            const list = this.triggers();

            if (list.length === 0 || this.index === -1) {
                return;
            }

            this.index = (this.index - 1 + list.length) % list.length;
            this.total = list.length;
            this.photo = this.photoFromTrigger(list[this.index]);
        },

        close() {
            this.open = false;
            this.photo = null;
            this.index = -1;
            document.body.classList.remove('overflow-hidden');
        },
    };
};

async function initStaticMaps() {
    const elements = document.querySelectorAll('.fotometro-static-map');

    if (elements.length === 0) {
        return;
    }

    const maplibregl = await loadMapLibre();

    elements.forEach((element) => {
        const { latitude, longitude, label, statusColor, line, lineStations, lineColor } = element.dataset;
        const mapConfig = buildMapConfig(element.dataset);
        const usableMaxZoom = mapConfig.maxZoom;

        if (! mapConfig.hasBasemapConfig) {
            element.innerHTML = `<div class="grid h-full place-items-center p-6 text-center text-sm text-black/60">${basemapConfigurationMessage(mapConfig)}</div>`;
            return;
        }

        const map = new maplibregl.Map({
            container: element,
            style: resolveMapStyle(mapConfig),
            center: longitude && latitude ? [Number(longitude), Number(latitude)] : [2.3522, 48.8566],
            zoom: longitude && latitude ? Math.min(14, usableMaxZoom) : Math.min(11, usableMaxZoom),
            maxZoom: usableMaxZoom,
            interactive: false,
            attributionControl: false,
        });

        if (mapConfig.attribution) {
            map.addControl(new maplibregl.AttributionControl({
                customAttribution: mapConfig.attribution,
                compact: true,
            }));
        }

        map.on('load', () => {
            if (longitude && latitude) {
                const marker = document.createElement('div');
                marker.className = 'h-4 w-4 rounded-full border-2 border-white shadow';
                marker.style.backgroundColor = statusColor || '#151515';
                marker.setAttribute('aria-label', label || 'Station');

                new maplibregl.Marker({ element: marker })
                    .setLngLat([Number(longitude), Number(latitude)])
                    .addTo(map);
            }

            if (line) {
                const feature = JSON.parse(line);
                map.addSource('line-preview', {
                    type: 'geojson',
                    data: feature,
                });
                map.addLayer({
                    id: 'line-preview-layer',
                    type: 'line',
                    source: 'line-preview',
                    paint: {
                        'line-color': lineColor || '#151515',
                        'line-width': 5,
                    },
                });

                const coordinates = feature.geometry?.coordinates || [];
                if (coordinates.length > 0) {
                    const bounds = coordinates.reduce(
                        (mapBounds, coordinate) => mapBounds.extend(coordinate),
                        new maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
                    );
                    map.fitBounds(bounds, { padding: 48, maxZoom: Math.min(13, usableMaxZoom), duration: 0 });
                }

                return;
            }

            if (lineStations) {
                const stations = JSON.parse(lineStations);
                const coordinates = stations
                    .filter((station) => Number.isFinite(Number(station.longitude)) && Number.isFinite(Number(station.latitude)))
                    .map((station) => {
                        const coordinate = [Number(station.longitude), Number(station.latitude)];
                        const marker = document.createElement('div');
                        marker.className = 'h-3 w-3 rounded-full border-2 border-white shadow';
                        marker.style.backgroundColor = station.status_color || lineColor || '#151515';
                        marker.setAttribute('aria-label', station.name || label || 'Station');

                        new maplibregl.Marker({ element: marker })
                            .setLngLat(coordinate)
                            .addTo(map);

                        return coordinate;
                    });

                if (coordinates.length === 1) {
                    map.flyTo({ center: coordinates[0], zoom: Math.min(14, usableMaxZoom), duration: 0 });
                } else if (coordinates.length > 1) {
                    const bounds = coordinates.reduce(
                        (mapBounds, coordinate) => mapBounds.extend(coordinate),
                        new maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
                    );

                    map.fitBounds(bounds, { padding: 48, maxZoom: Math.min(13, usableMaxZoom), duration: 0 });
                }
            }
        });
    });
}

initStaticMaps();
