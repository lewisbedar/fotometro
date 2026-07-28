<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnostic conteneur de plan - fotométro</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-neutral-100">
    <section class="line-diagram-panel">
        <div class="line-diagram-header mb-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold">Diagnostic conteneur SVG</h1>
            <span class="text-sm text-black/60">SVG 3000 x 300</span>
        </div>

        <div class="line-diagram-scroll">
            <div class="line-diagram-host">
                <svg class="line-diagram-svg" width="3000" height="300" viewBox="0 0 3000 300" role="img" aria-label="Plan factice">
                    <rect width="3000" height="300" fill="#fff7ed"></rect>
                    <line x1="40" y1="150" x2="2960" y2="150" stroke="#ff0000" stroke-width="12" stroke-linecap="round"></line>
                    @foreach (range(0, 29) as $index)
                        <circle cx="{{ 60 + ($index * 100) }}" cy="150" r="14" fill="#ffffff" stroke="#111827" stroke-width="4"></circle>
                        <text x="{{ 60 + ($index * 100) }}" y="115" font-size="16" text-anchor="middle">S{{ $index + 1 }}</text>
                    @endforeach
                </svg>
            </div>
        </div>
    </section>

    <script>
        window.addEventListener('load', () => {
            const panel = document.querySelector('.line-diagram-panel');
            const scroll = document.querySelector('.line-diagram-scroll');
            const host = document.querySelector('.line-diagram-host');
            const svg = document.querySelector('.line-diagram-svg');

            console.table({
                panel: {
                    width: panel?.getBoundingClientRect().width,
                    left: panel?.getBoundingClientRect().left,
                    right: panel?.getBoundingClientRect().right,
                },
                scroll: {
                    width: scroll?.getBoundingClientRect().width,
                    clientWidth: scroll?.clientWidth,
                    scrollWidth: scroll?.scrollWidth,
                    overflowX: scroll ? getComputedStyle(scroll).overflowX : null,
                },
                host: {
                    width: host?.getBoundingClientRect().width,
                    computedWidth: host ? getComputedStyle(host).width : null,
                },
                svg: {
                    width: svg?.getBoundingClientRect().width,
                    attrWidth: svg?.getAttribute('width'),
                    viewBox: svg?.getAttribute('viewBox'),
                    position: svg ? getComputedStyle(svg).position : null,
                },
            });
        });
    </script>
</body>
</html>
