<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Diagnostic MapLibre - fotometro</title>
        @vite(['resources/css/app.css', 'resources/js/map-diagnostic.js'])
    </head>
    <body class="bg-white">
        <main class="min-h-screen p-4">
            <h1 class="mb-4 text-xl font-semibold">Diagnostic MapLibre minimal</h1>
            <div class="mb-4 rounded-md border border-black/10 bg-white p-4 text-sm leading-6 text-black/70">
                <p><strong>Configuration retenue :</strong> fond raster MapLibre.</p>
                <p>Utilisez <code>?style=raster</code> pour le mode retenu et <code>?style=vector</code> pour tester le style vectoriel CARTO expérimental.</p>
            </div>
            <div id="metro-map-diagnostic" class="h-[calc(100vh-6rem)] min-h-[500px] w-full border border-black/10"></div>
        </main>
    </body>
</html>
