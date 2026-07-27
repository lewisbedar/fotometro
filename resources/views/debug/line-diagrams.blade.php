<x-layouts.app title="Diagnostic schemas de ligne - fotometro" :full-width="true">
    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-black/45">Diagnostic local</p>
            <h1 class="mt-2 text-3xl font-semibold">Schemas topologiques</h1>
            <p class="mt-2 max-w-3xl text-sm text-black/60">Rendu serveur des layouts SVG calcules par Laravel. Cette page existe uniquement en environnement local.</p>
            <nav class="mt-4 flex flex-wrap gap-2 text-sm">
                @foreach (['7', '7B', '10', '13'] as $code)
                    <a class="rounded-md border border-black/10 bg-white px-3 py-2 font-semibold hover:bg-black hover:text-white" href="#line-{{ $code }}">Ligne {{ $code }}</a>
                @endforeach
            </nav>
        </div>

        <div class="space-y-8">
            @foreach ($lines as $line)
                @php($layout = $line['topology']['layout'])
                <article id="line-{{ $line['code'] }}" class="rounded-lg border border-black/10 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="line-code" style="background: {{ $line['color'] }}; color: {{ $line['text_color'] }}">{{ $line['code'] }}</span>
                            <div>
                                <h2 class="text-xl font-semibold">{{ $line['name'] }}</h2>
                                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-black/60">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="h-4 w-8 rounded border border-black/10" style="background: {{ $line['color'] }}"></span>
                                        color {{ $line['color'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <span class="h-4 w-8 rounded border border-black/10" style="background: {{ $line['text_color'] }}"></span>
                                        text {{ $line['text_color'] }}
                                    </span>
                                </div>
                                <p class="text-sm text-black/60">{{ $layout['type'] }} · {{ $layout['width'] }} x {{ $layout['height'] }} · viewBox {{ $layout['view_box']['value'] }}</p>
                            </div>
                        </div>
                        <dl class="text-right text-xs text-black/60">
                            <dt class="font-semibold text-black/70">Terminus</dt>
                            <dd>{{ implode(' / ', $layout['terminus']) ?: 'A completer' }}</dd>
                            <dt class="mt-2 font-semibold text-black/70">Branches</dt>
                            <dd>{{ collect($layout['branches'])->pluck('key')->implode(' / ') ?: 'Aucune' }}</dd>
                        </dl>
                    </div>

                    <details class="mb-3 rounded-md border border-black/10 p-3 text-xs">
                        <summary class="cursor-pointer font-semibold">Afficher la grille de coordonnees</summary>
                        <p class="mt-2 text-black/60">Le cadre pointille represente les limites du SVG. Padding calcule: {{ $layout['view_box']['padding'] }} px.</p>
                        @if (in_array($line['code'], ['10', '13'], true))
                            <div class="mt-3 overflow-x-auto">
                                <table class="min-w-full text-left">
                                    <thead>
                                        <tr class="border-b border-black/10">
                                            <th class="py-1 pr-3">Station</th>
                                            <th class="py-1 pr-3">External ID</th>
                                            <th class="py-1 pr-3">Branche</th>
                                            <th class="py-1 pr-3">Ordre</th>
                                            <th class="py-1 pr-3">Role</th>
                                            <th class="py-1 pr-3">x</th>
                                            <th class="py-1 pr-3">y</th>
                                            <th class="py-1 pr-3">Label x</th>
                                            <th class="py-1 pr-3">Label y</th>
                                            <th class="py-1 pr-3">Anchor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($layout['stations'] as $station)
                                            <tr class="border-b border-black/5">
                                                <td class="py-1 pr-3">{{ $station['name'] }}</td>
                                                <td class="py-1 pr-3">{{ $station['external_id'] ?? '' }}</td>
                                                <td class="py-1 pr-3">{{ $station['branch_key'] ?? $station['branch'] ?? 'main' }}</td>
                                                <td class="py-1 pr-3">{{ $station['diagram_order'] ?? '' }}</td>
                                                <td class="py-1 pr-3">{{ $station['diagram_role'] ?? '' }}</td>
                                                <td class="py-1 pr-3">{{ $station['x'] }}</td>
                                                <td class="py-1 pr-3">{{ $station['y'] }}</td>
                                                <td class="py-1 pr-3">{{ $station['label_x'] }}</td>
                                                <td class="py-1 pr-3">{{ $station['label_y'] }}</td>
                                                <td class="py-1 pr-3">{{ $station['label_anchor'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </details>

                    <div class="overflow-x-auto">
                        <svg class="line-diagram-svg debug-line-diagram-svg" viewBox="{{ $layout['view_box']['value'] }}" width="{{ $layout['width'] }}" height="{{ $layout['height'] }}" style="--line-color: {{ $line['color'] }}; --fotometro-terminus-blue: {{ config('line_diagrams.terminus_blue') }}">
                            <rect class="debug-viewbox-frame" x="1" y="1" width="{{ $layout['width'] - 2 }}" height="{{ $layout['height'] - 2 }}" />
                            @foreach ($layout['debug_guides'] ?? [] as $guide)
                                <line class="debug-horizontal-guide" x1="0" y1="{{ $guide['y'] }}" x2="{{ $layout['width'] }}" y2="{{ $guide['y'] }}" />
                                <text class="debug-guide-label" x="12" y="{{ $guide['y'] - 6 }}">{{ $guide['label'] ?? $guide['id'] }}</text>
                            @endforeach
                            @foreach ($layout['segments'] as $segment)
                                <line class="diagram-segment-underlay" x1="{{ $segment['x1'] }}" y1="{{ $segment['y1'] }}" x2="{{ $segment['x2'] }}" y2="{{ $segment['y2'] }}" />
                            @endforeach
                            @foreach ($layout['segments'] as $segment)
                                <line class="diagram-segment is-{{ $segment['kind'] }}" x1="{{ $segment['x1'] }}" y1="{{ $segment['y1'] }}" x2="{{ $segment['x2'] }}" y2="{{ $segment['y2'] }}" />
                            @endforeach
                            @foreach ($layout['stations'] as $station)
                                <g class="diagram-svg-station {{ $station['is_terminus'] ? 'is-terminus' : '' }}" data-station-id="{{ $station['id'] }}">
                                    <line class="debug-label-anchor-guide" x1="{{ $station['x'] }}" y1="{{ $station['y'] }}" x2="{{ $station['label_x'] }}" y2="{{ $station['label_y'] }}" />
                                    <circle class="debug-label-anchor-point" cx="{{ $station['label_x'] }}" cy="{{ $station['label_y'] }}" r="3" />
                                    <circle class="diagram-svg-node status-{{ $station['coverage_status']['value'] }}" cx="{{ $station['x'] }}" cy="{{ $station['y'] }}" r="7" />
                                    <g transform="rotate({{ $station['label_rotation'] }} {{ $station['label_x'] }} {{ $station['label_y'] }})">
                                        <rect class="debug-label-bounds" x="{{ $station['label_anchor'] === 'end' ? $station['label_x'] - $station['label_width'] : $station['label_x'] - ($station['label_width'] / 2) }}" y="{{ $station['label_y'] - 5 }}" width="{{ $station['label_width'] }}" height="24" />
                                        @if ($station['is_terminus'])
                                            <rect class="diagram-svg-terminus-box" x="{{ $station['terminus_label_box']['x'] }}" y="{{ $station['terminus_label_box']['y'] }}" width="{{ $station['terminus_label_box']['width'] }}" height="{{ $station['terminus_label_box']['height'] }}" rx="{{ $station['terminus_label_box']['rx'] }}" />
                                        @endif
                                        <text class="diagram-svg-label {{ $station['is_terminus'] ? 'is-terminus' : '' }}" x="{{ $station['label_x'] }}" y="{{ $station['label_y'] }}" text-anchor="{{ $station['label_anchor'] }}">{{ $station['name'] }}</text>
                                    </g>
                                    @foreach ($station['connection_badges'] as $connection)
                                        <circle class="diagram-svg-connection-circle" cx="{{ $connection['x'] }}" cy="{{ $connection['y'] }}" r="8" style="fill: {{ $connection['color'] }}" />
                                        <text class="diagram-svg-connection-text" x="{{ $connection['x'] }}" y="{{ $connection['y'] + 3 }}" text-anchor="middle" style="fill: {{ $connection['text_color'] }}">{{ $connection['code'] }}</text>
                                    @endforeach
                                </g>
                            @endforeach
                        </svg>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</x-layouts.app>
