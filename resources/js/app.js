import 'maplibre-gl/dist/maplibre-gl.css';

let maplibrePromise = null;

async function loadMapLibre() {
    maplibrePromise ??= import('maplibre-gl');

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
    return {
        map: null,
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
        progressCollapsed: false,
        showStations: true,
        showLineTracks: true,
        showConnections: true,
        showEntrances: false,
        enabledStatuses: ['not_started', 'planned', 'in_progress', 'documented', 'complete'],
        searchQuery: '',
        searchResults: [],
        focusedSearchIndex: -1,
        searchLoading: false,
        maplibregl: null,
        mapFatalError: null,
        mapWarnings: [],
        mapHasLoaded: false,
        mapStyleHasLoaded: false,

        async init() {
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

        async loadMapData() {
            const response = await fetch(this.mapEndpoint, {
                headers: { Accept: 'application/json' },
            });

            this.mapData = await response.json();
        },

        async createMap() {
            if (this.map) {
                return;
            }

            this.maplibregl = await loadMapLibre();
            const container = document.getElementById('metro-map');

            if (! container) {
                throw new Error('Map container #metro-map was not found.');
            }

            if (! this.hasBasemapConfig) {
                return;
            }

            try {
                this.map = new this.maplibregl.Map({
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

                this.map.addControl(new this.maplibregl.NavigationControl({ visualizePitch: false }), 'top-right');

                if (this.basemapConfig.attribution) {
                    this.map.addControl(new this.maplibregl.AttributionControl({
                        customAttribution: this.basemapConfig.attribution,
                        compact: true,
                    }));
                }

                this.map.on('style.load', () => {
                    this.mapStyleHasLoaded = true;
                });

                this.map.on('load', () => {
                    this.mapHasLoaded = true;

                    if (import.meta.env.DEV) {
                        console.debug('[fotometro] map loaded', {
                            center: this.map.getCenter(),
                            zoom: this.map.getZoom(),
                            basemapDriver: this.basemapConfig.basemapDriver,
                        });
                    }

                    const canvas = this.map.getCanvas();

                    if (canvas.width === 0 || canvas.height === 0 || canvas.clientWidth === 0 || canvas.clientHeight === 0) {
                        this.reportFatalMapError('Map canvas has no drawable size', 'Canvas dimensions are zero.');
                    }

                    this.map.resize();
                    this.addSourcesAndLayers();
                    this.fitToVisibleData();

                    requestAnimationFrame(() => this.map.resize());
                });

                this.map.on('error', (event) => {
                    const details = describeMapError(event);

                    if (this.isLocal || import.meta.env.DEV) {
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
                    if (! this.map) {
                        return;
                    }

                    if (! this.mapHasLoaded && ! this.mapStyleHasLoaded) {
                        this.reportFatalMapError('MapLibre did not finish loading', 'No load or style.load event after 5 seconds.');
                    }
                }, 5000);

                requestAnimationFrame(() => this.map?.resize());
            } catch (error) {
                this.reportFatalMapError('Map initialization failed', error);
            }
        },

        addSourcesAndLayers() {
            this.map.addSource('fotometro-lines', {
                type: 'geojson',
                data: this.lineFeatureCollection(),
            });

            this.map.addSource('fotometro-stations', {
                type: 'geojson',
                data: this.stationFeatureCollection(),
            });

            this.map.addLayer({
                id: 'fotometro-lines-layer',
                type: 'line',
                source: 'fotometro-lines',
                paint: {
                    'line-color': ['get', 'color'],
                    'line-width': ['case', ['==', ['get', 'selected'], true], 7, 4],
                    'line-opacity': ['case', ['==', ['get', 'dimmed'], true], 0.18, 0.85],
                },
            });

            this.map.addLayer({
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

            this.map.on('click', 'fotometro-stations-layer', (event) => {
                const stationId = event.features?.[0]?.properties?.station_id;
                if (stationId) {
                    this.selectStation(Number(stationId), false);
                }
            });

            this.map.on('mouseenter', 'fotometro-stations-layer', () => {
                this.map.getCanvas().style.cursor = 'pointer';
            });

            this.map.on('mouseleave', 'fotometro-stations-layer', () => {
                this.map.getCanvas().style.cursor = '';
            });
        },

        lineFeatureCollection() {
            return {
                type: 'FeatureCollection',
                features: this.mapData.lines
                    .filter((line) => line.path_geojson?.geometry)
                    .map((line) => ({
                        ...line.path_geojson,
                        properties: {
                            line_id: line.id,
                            code: line.code,
                            color: line.color,
                            selected: this.selectedLineId === line.id,
                            dimmed: this.selectedLineId !== null && this.selectedLineId !== line.id,
                        },
                    })),
            };
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
            if (! this.map) {
                return;
            }

            this.map.getSource('fotometro-lines')?.setData(this.lineFeatureCollection());
            this.map.getSource('fotometro-stations')?.setData(this.stationFeatureCollection());
            this.refreshLayerVisibility();
        },

        refreshLayerVisibility() {
            if (! this.map) {
                return;
            }

            if (this.map.getLayer('fotometro-stations-layer')) {
                this.map.setLayoutProperty('fotometro-stations-layer', 'visibility', this.showStations ? 'visible' : 'none');
            }

            if (this.map.getLayer('fotometro-lines-layer')) {
                this.map.setLayoutProperty('fotometro-lines-layer', 'visibility', this.showLineTracks ? 'visible' : 'none');
            }
        },

        toggleFiltersPanel() {
            this.isFiltersOpen = ! this.isFiltersOpen;

            if (this.isFiltersOpen) {
                this.isLinesOpen = false;
                this.activePanel = 'filters';
            } else if (this.activePanel === 'filters') {
                this.activePanel = null;
            }
        },

        toggleLinesPanel() {
            this.isLinesOpen = ! this.isLinesOpen;

            if (this.isLinesOpen) {
                this.isFiltersOpen = false;
                this.activePanel = 'lines';
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
            this.selectedLineId = this.selectedLineId === lineId ? null : lineId;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.refreshVisibility();

            if (this.selectedLineId === null) {
                this.isLineDiagramOpen = false;
                this.fitToVisibleData();
                return;
            }

            const line = this.mapData.lines.find((candidate) => Number(candidate.id) === Number(this.selectedLineId));

            if (line) {
                this.isLineDiagramOpen = true;
                this.isLinesOpen = false;
                this.activePanel = null;
                this.fitMapToLine(line);
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

            if (this.map && station.coordinates) {
                this.map.flyTo({
                    center: station.coordinates,
                    zoom: Math.min(Math.max(this.map.getZoom(), fromSearch ? 14 : 13), this.mapUsableMaxZoom),
                    essential: false,
                });
                this.openStationPopup(station);
            }

            this.scrollDiagramToStation(station.id);
        },

        findStation(stationId) {
            return this.mapData.stations.find((station) => station.id === stationId);
        },

        openStationPopup(station) {
            const content = document.createElement('div');
            content.className = 'space-y-2 text-sm';

            const title = document.createElement('strong');
            title.className = 'block text-base';
            title.textContent = station.name;
            content.appendChild(title);

            const lines = document.createElement('div');
            lines.className = 'flex flex-wrap gap-1';
            this.normalizeLines(station.lines).forEach((line) => {
                const badge = document.createElement('span');
                badge.className = 'rounded-full px-2 py-0.5 text-xs font-bold';
                badge.style.backgroundColor = line.color;
                badge.style.color = line.text_color;
                badge.textContent = line.code;
                lines.appendChild(badge);
            });
            content.appendChild(lines);

            const status = document.createElement('p');
            status.textContent = station.coverage_status.description;
            content.appendChild(status);

            const link = document.createElement('a');
            link.href = station.url;
            link.className = 'inline-flex rounded bg-black px-2 py-1 font-semibold text-white';
            link.textContent = 'Voir la station';
            content.appendChild(link);

            new this.maplibregl.Popup({ closeButton: true, closeOnClick: false })
                .setLngLat(station.coordinates)
                .setDOMContent(content)
                .addTo(this.map);
        },

        clearSelection() {
            this.clearStationSelection();
        },

        clearStationSelection() {
            this.selectedStation = null;
            this.selectedStationId = null;
            this.refreshVisibility();
        },

        clearLineSelection() {
            this.selectedLineId = null;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.isLineDiagramOpen = false;
            this.refreshVisibility();
            this.fitToVisibleData();
        },

        resetFilters() {
            this.selectedLineId = null;
            this.selectedStation = null;
            this.selectedStationId = null;
            this.enabledStatuses = this.mapData.coverage_statuses.map((status) => status.value);
            this.searchQuery = '';
            this.searchResults = [];
            this.focusedSearchIndex = -1;
            this.isFiltersOpen = false;
            this.isLinesOpen = false;
            this.isLineDiagramOpen = false;
            this.activePanel = null;
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
            if (! this.map) {
                return;
            }

            const coordinates = [];

            this.visibleStations().forEach((station) => {
                if (station.coordinates) {
                    coordinates.push(station.coordinates);
                }
            });

            this.lineFeatureCollection().features.forEach((feature) => {
                feature.geometry.coordinates.forEach((coordinate) => coordinates.push(coordinate));
            });

            if (coordinates.length === 0) {
                return;
            }

            const bounds = coordinates.reduce(
                (mapBounds, coordinate) => mapBounds.extend(coordinate),
                new this.maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
            );

            this.map.fitBounds(bounds, {
                padding: 64,
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
            const coordinates = pathGeojson?.geometry?.coordinates;

            if (! Array.isArray(coordinates)) {
                return [];
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

        fitToCoordinates(coordinates) {
            if (! this.map || coordinates.length === 0) {
                return;
            }

            if (coordinates.length === 1) {
                this.map.flyTo({
                    center: coordinates[0],
                    zoom: Math.min(14, this.mapUsableMaxZoom),
                    duration: 700,
                });

                return;
            }

            const bounds = coordinates.reduce(
                (result, coordinate) => result.extend(coordinate),
                new this.maplibregl.LngLatBounds(coordinates[0], coordinates[0]),
            );

            this.map.fitBounds(bounds, {
                padding: 80,
                maxZoom: Math.min(14, this.mapUsableMaxZoom),
                duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 700,
            });
        },

        async searchStations() {
            this.focusedSearchIndex = -1;

            if (this.searchQuery.trim().length < 2) {
                this.searchResults = [];
                return;
            }

            this.searchLoading = true;

            const url = new URL(this.searchEndpoint, window.location.origin);
            url.searchParams.set('q', this.searchQuery);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const payload = await response.json();

            this.searchResults = payload.data || [];
            this.searchLoading = false;
        },

        moveSearchFocus(direction) {
            if (this.searchResults.length === 0) {
                return;
            }

            this.focusedSearchIndex = (this.focusedSearchIndex + direction + this.searchResults.length) % this.searchResults.length;
        },

        chooseFocusedSearchResult() {
            if (this.focusedSearchIndex < 0 || ! this.searchResults[this.focusedSearchIndex]) {
                return;
            }

            this.selectStation(this.searchResults[this.focusedSearchIndex].id, true);
        },

        closeSearch() {
            this.searchResults = [];
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

        selectStationFromDiagram(stationId) {
            this.selectStation(Number(stationId), true);
        },

        scrollDiagramToStation(stationId) {
            requestAnimationFrame(() => {
                const safeId = Number(stationId);

                if (! Number.isInteger(safeId)) {
                    return;
                }

                const selector = `[data-station-id="${safeId}"]`;
                this.$refs.lineDiagramScroller?.querySelectorAll('.is-active-occurrence').forEach((element) => element.classList.remove('is-active-occurrence'));
                this.$refs.lineDiagramMobileScroller?.querySelectorAll('.is-active-occurrence').forEach((element) => element.classList.remove('is-active-occurrence'));
                this.$refs.lineDiagramScroller?.querySelectorAll(selector).forEach((element, index) => {
                    element.classList.toggle('is-active-occurrence', true);

                    if (index === 0) {
                        element.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
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

        topologyTypeLabel(line) {
            const labels = {
                simple: 'Ligne simple',
                branched: 'Ligne a branches',
                loop: 'Boucle',
                'partial-loop': 'Boucle partielle',
                'loop-with-mainline': 'Boucle avec axe principal',
            };

            return labels[line?.topology?.type] || 'Topologie';
        },

        lineTerminusLabel(line) {
            const orientation = line?.topology?.orientation;

            if (orientation?.start && Array.isArray(orientation.ends) && orientation.ends.length > 0) {
                return `${orientation.start.name} - ${orientation.ends.map((station) => station.name).join(' / ')}`;
            }

            const termini = this.uniqueTopologyStations(line).filter((station) => station.is_terminus);

            if (termini.length >= 2) {
                return `${termini[0].name} - ${termini[termini.length - 1].name}`;
            }

            if (termini.length === 1) {
                return `Terminus: ${termini[0].name}`;
            }

            const stations = this.uniqueTopologyStations(line);

            if (stations.length >= 2) {
                return `${stations[0].name} - ${stations[stations.length - 1].name}`;
            }

            return 'Terminus a completer';
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
            this.map?.remove();
            this.map = null;
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
