<section class="line-diagram-panel map-glass" x-show="selectedLine && isLineDiagramOpen" x-transition x-cloak aria-labelledby="line-diagram-title">
    <template x-if="selectedLine">
        <div>
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="line-code" :style="`background:${safeLineColor(selectedLine.color)};color:${safeLineColor(selectedLine.text_color)}`" x-text="selectedLine.code"></span>
                    <div>
                        <h2 id="line-diagram-title" class="text-xl font-semibold" x-text="selectedLine.name"></h2>
                        <p class="text-sm text-black/60" x-text="lineTerminusLabel(selectedLine)"></p>
                    </div>
                </div>
                <button type="button" class="map-secondary-button" x-on:click="isLineDiagramOpen = false">Masquer le plan</button>
            </div>

            <div class="mt-5 hidden overflow-x-auto pb-4 md:block" x-ref="lineDiagramScroller">
                <ol class="line-diagram-horizontal" :style="`--line-color:${safeLineColor(selectedLine.color)}`">
                    <template x-for="station in orderedLineStations(selectedLine)" :key="station.id">
                        <li class="line-diagram-stop" x-bind:data-station-id="station.id">
                            <button type="button" class="diagram-node" x-bind:class="coverageNodeClass(station)" x-on:click="selectStationFromDiagram(station.id)">
                                <span class="sr-only" x-text="station.name"></span>
                            </button>
                            <span class="diagram-station-name" x-text="station.name"></span>
                            <span class="diagram-terminus" x-show="station.is_terminus">Terminus</span>
                            <span class="diagram-connections" x-show="showConnections">
                                <template x-for="connection in station.connections" :key="connection.id">
                                    <span class="connection-code" :style="`background:${safeLineColor(connection.color)};color:${safeLineColor(connection.text_color)}`" x-text="connection.code"></span>
                                </template>
                            </span>
                        </li>
                    </template>
                </ol>
            </div>

            <div class="mt-5 max-h-[44dvh] overflow-y-auto md:hidden" x-ref="lineDiagramMobileScroller">
                <ol class="line-diagram-vertical" :style="`--line-color:${safeLineColor(selectedLine.color)}`">
                    <template x-for="station in orderedLineStations(selectedLine)" :key="station.id">
                        <li class="line-diagram-mobile-stop" x-bind:data-station-id="station.id">
                            <button type="button" class="diagram-node" x-bind:class="coverageNodeClass(station)" x-on:click="selectStationFromDiagram(station.id)"></button>
                            <div>
                                <p class="font-semibold" x-text="station.name"></p>
                                <p class="text-xs text-black/55" x-show="station.is_terminus">Terminus</p>
                                <div class="mt-1 flex flex-wrap gap-1" x-show="selectedStation?.id === station.id">
                                    <template x-for="connection in station.connections" :key="connection.id">
                                        <span class="connection-code" :style="`background:${safeLineColor(connection.color)};color:${safeLineColor(connection.text_color)}`" x-text="connection.code"></span>
                                    </template>
                                </div>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>

            @include('partials.map.map-legend')
        </div>
    </template>
</section>
