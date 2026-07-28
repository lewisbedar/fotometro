<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\View\View;

class PublicStationController extends Controller
{
    public function show(Station $station): View
    {
        abort_unless($station->is_active, 404);

        $station->load([
            'lines' => fn ($query) => $query->orderBy('sort_order'),
            'photos' => fn ($query) => $query->publiclyVisible()->with(['category', 'stationAccess'])->orderBy('sort_order')->latest(),
            'accesses' => fn ($query) => $query->orderBy('name'),
        ]);

        return view('stations.show', [
            'station' => $station,
            'photos' => $station->photos,
            'photoCategories' => $station->photos->pluck('category')->filter()->unique('id')->values(),
            'photoAccesses' => $station->photos->pluck('stationAccess')->filter()->unique('id')->values(),
            'mapConfig' => config('fotometro.map'),
            'metaDescription' => trim("Station {$station->name}. ".($station->description ?? 'Fiche publique du catalogue photographique fotometro.')),
        ]);
    }
}
