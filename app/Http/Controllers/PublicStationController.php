<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\View\View;

class PublicStationController extends Controller
{
    public function show(Station $station): View
    {
        abort_unless($station->is_active, 404);

        $station->load(['lines' => fn ($query) => $query->orderBy('sort_order')]);

        return view('stations.show', [
            'station' => $station,
            'mapConfig' => config('fotometro.map'),
            'metaDescription' => trim("Station {$station->name}. ".($station->description ?? 'Fiche publique du catalogue photographique fotometro.')),
        ]);
    }
}
