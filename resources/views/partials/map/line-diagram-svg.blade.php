<section class="line-diagram-panel map-glass" x-show="selectedLine" x-cloak :aria-label="`Plan de la ligne ${selectedLine?.code ?? ''}`">
    <template x-if="selectedLine">
        <div class="line-diagram-content">
            <button
                type="button"
                class="line-diagram-handle flex w-full items-center justify-center gap-2"
                x-on:click="toggleLineDiagram()"
                :aria-expanded="isLineDiagramOpen.toString()"
                aria-controls="line-diagram-body"
                aria-label="Afficher ou masquer le plan de la ligne"
            >
                <span class="text-sm font-semibold text-black/60">Plan de la ligne</span>
                <span class="line-diagram-chevron" :class="{ 'is-open': isLineDiagramOpen }" aria-hidden="true"></span>
            </button>

            <div class="line-diagram-collapse" :class="{ 'is-open': isLineDiagramOpen }">
                <div
                    id="line-diagram-body"
                    class="flex min-h-0 flex-col"
                    :aria-hidden="(! isLineDiagramOpen).toString()"
                    :inert="! isLineDiagramOpen"
                >
                    <template x-if="hasSelectedLineLayout">
                        <div class="line-diagram-scroll mt-1 hidden pb-4 md:block" x-ref="lineDiagramScroll">
                            <div
                                class="line-diagram-host"
                                x-ref="lineDiagramSvgHost"
                                :style="`--line-color:${safeLineColor(selectedLine.color)};--fotometro-terminus-blue:{{ config('line_diagrams.terminus_blue') }}`"
                            ></div>
                        </div>
                    </template>

                    <template x-if="! hasSelectedLineLayout">
                        <div class="mt-4 rounded-md bg-black/5 p-4 text-sm text-black/65">
                            <p class="font-semibold">Le plan de cette ligne n’est pas disponible.</p>
                            <p class="mt-1" x-show="lineDiagramError" x-text="lineDiagramError"></p>
                        </div>
                    </template>

                    <div class="mt-4 max-h-[44dvh] overflow-y-auto md:hidden" x-ref="lineDiagramMobileScroller">
                        <div class="line-topology-mobile" :style="`--line-color:${safeLineColor(selectedLine.color)}`">
                            <template x-for="branch in topologyBranches(selectedLine)" :key="branch.key">
                                <section class="line-topology-mobile-branch">
                                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-black/45" x-show="topologyBranches(selectedLine).length > 1" x-text="branch.label"></p>
                                    <ol class="line-diagram-vertical">
                                        <template x-for="station in (branch.stations ?? [])" :key="`${branch.key}-${station.id}-${station.position}`">
                                            <li class="line-diagram-mobile-stop" x-bind:data-station-id="station.id">
                                                <button type="button" class="diagram-node" x-bind:class="coverageNodeClass(station)" x-on:click="selectStationFromDiagram(station.id)">
                                                    <span class="sr-only" x-text="station.name"></span>
                                                </button>
                                                <div>
                                                    <p class="font-semibold" x-text="station.name"></p>
                                                    <p class="text-xs text-black/55" x-show="station.is_terminus">Terminus</p>
                                                    <div class="mt-1 flex flex-wrap gap-1" x-show="selectedStation?.id === station.id && showConnections">
                                                        <template x-for="connection in (station.connections ?? [])" :key="connection.id">
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
            </div>
        </div>
    </template>
</section>
