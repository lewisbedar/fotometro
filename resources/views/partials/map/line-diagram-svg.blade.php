<section class="line-diagram-panel map-glass" x-show="selectedLine && isLineDiagramOpen" x-transition x-cloak aria-labelledby="line-diagram-title">
    <template x-if="selectedLine">
        <div>
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="line-code" :style="`background:${safeLineColor(selectedLine.color)};color:${safeLineColor(selectedLine.text_color)}`" x-text="selectedLine.code"></span>
                    <div>
                        <h2 id="line-diagram-title" class="text-xl font-semibold" x-text="selectedLine.name"></h2>
                        <p class="text-sm text-black/60" x-text="lineTerminusLabel(selectedLine)"></p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-black/45" x-text="topologyTypeLabel(selectedLine)"></p>
                    </div>
                </div>
                <button type="button" class="map-secondary-button" x-on:click="isLineDiagramOpen = false">Masquer le plan</button>
            </div>

            <div class="mt-5 hidden overflow-x-auto pb-4 md:block" x-ref="lineDiagramScroller">
                <svg
                    class="line-diagram-svg"
                    role="group"
                    aria-labelledby="line-diagram-title"
                    x-bind:viewBox="selectedLine.topology.layout.view_box.value"
                    x-bind:width="selectedLine.topology.layout.width"
                    x-bind:height="selectedLine.topology.layout.height"
                    x-bind:data-layout-type="selectedLine.topology.layout.type"
                    :style="`--line-color:${safeLineColor(selectedLine.color)};--fotometro-terminus-blue:{{ config('line_diagrams.terminus_blue') }}`"
                >
                    <g class="diagram-segments diagram-segments-underlay">
                        <template x-for="segment in selectedLine.topology.layout.segments" :key="`underlay-${segment.id}`">
                            <line class="diagram-segment-underlay" x-bind:x1="segment.x1" x-bind:y1="segment.y1" x-bind:x2="segment.x2" x-bind:y2="segment.y2" />
                        </template>
                    </g>
                    <g class="diagram-segments">
                        <template x-for="segment in selectedLine.topology.layout.segments" :key="segment.id">
                            <line class="diagram-segment" x-bind:class="`is-${segment.kind}`" x-bind:x1="segment.x1" x-bind:y1="segment.y1" x-bind:x2="segment.x2" x-bind:y2="segment.y2" />
                        </template>
                    </g>
                    <g class="diagram-stations">
                        <template x-for="station in selectedLine.topology.layout.stations" :key="station.occurrence_key">
                            <g
                                class="diagram-svg-station"
                                x-bind:class="{ 'is-selected': Number(selectedStationId) === Number(station.id), 'is-terminus': station.is_terminus }"
                                x-bind:data-station-id="station.id"
                                role="button"
                                tabindex="0"
                                x-bind:aria-label="`Selectionner ${station.name}`"
                                x-on:click="selectStationFromDiagram(station.id)"
                                x-on:keydown.enter.prevent="selectStationFromDiagram(station.id)"
                                x-on:keydown.space.prevent="selectStationFromDiagram(station.id)"
                            >
                                <circle class="diagram-svg-node" x-bind:class="coverageSvgNodeClass(station)" x-bind:cx="station.x" x-bind:cy="station.y" r="7" />
                                <circle class="diagram-svg-selected-ring" x-show="Number(selectedStationId) === Number(station.id)" x-bind:cx="station.x" x-bind:cy="station.y" r="13" />
                                <g x-bind:transform="`rotate(${station.label_rotation} ${station.label_x} ${station.label_y})`">
                                    <rect
                                        class="diagram-svg-terminus-box"
                                        x-show="station.is_terminus && station.terminus_label_box"
                                        x-bind:x="station.terminus_label_box?.x"
                                        x-bind:y="station.terminus_label_box?.y"
                                        x-bind:width="station.terminus_label_box?.width"
                                        x-bind:height="station.terminus_label_box?.height"
                                        x-bind:rx="station.terminus_label_box?.rx"
                                    ></rect>
                                    <text
                                        class="diagram-svg-label"
                                        x-bind:class="{ 'is-terminus': station.is_terminus }"
                                        x-bind:x="station.label_x"
                                        x-bind:y="station.label_y"
                                        x-bind:text-anchor="station.label_anchor"
                                        x-text="station.name"
                                    ></text>
                                </g>
                                <g class="diagram-svg-connections" x-show="showConnections">
                                    <template x-for="connection in station.connection_badges" :key="`${station.occurrence_key}-${connection.id}`">
                                        <g>
                                            <circle class="diagram-svg-connection-circle" x-bind:cx="connection.x" x-bind:cy="connection.y" r="8" :style="`fill:${safeLineColor(connection.color)}`" />
                                            <text class="diagram-svg-connection-text" x-bind:x="connection.x" x-bind:y="connection.y + 3" text-anchor="middle" :style="`fill:${safeLineColor(connection.text_color)}`" x-text="connection.code"></text>
                                        </g>
                                    </template>
                                </g>
                            </g>
                        </template>
                    </g>
                </svg>
            </div>

            <div class="mt-5 max-h-[44dvh] overflow-y-auto md:hidden" x-ref="lineDiagramMobileScroller">
                <div class="line-topology-mobile" :style="`--line-color:${safeLineColor(selectedLine.color)}`">
                    <template x-for="branch in topologyBranches(selectedLine)" :key="branch.key">
                        <section class="line-topology-mobile-branch">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-black/45" x-show="topologyBranches(selectedLine).length > 1" x-text="branch.label"></p>
                            <ol class="line-diagram-vertical">
                                <template x-for="station in branch.stations" :key="`${branch.key}-${station.id}-${station.position}`">
                                    <li class="line-diagram-mobile-stop" x-bind:data-station-id="station.id">
                                        <button type="button" class="diagram-node" x-bind:class="coverageNodeClass(station)" x-on:click="selectStationFromDiagram(station.id)">
                                            <span class="sr-only" x-text="station.name"></span>
                                        </button>
                                        <div>
                                            <p class="font-semibold" x-text="station.name"></p>
                                            <p class="text-xs text-black/55" x-show="station.is_terminus">Terminus</p>
                                            <div class="mt-1 flex flex-wrap gap-1" x-show="selectedStation?.id === station.id && showConnections">
                                                <template x-for="connection in station.connections" :key="connection.id">
                                                    <span class="connection-code" :style="`background:${safeLineColor(connection.color)};color:${safeLineColor(connection.text_color)}`" x-text="connection.code"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ol>
                        </section>
                    </template>
                </div>
            </div>

            @include('partials.map.map-legend')
        </div>
    </template>
</section>
