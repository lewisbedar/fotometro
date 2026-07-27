<?php

namespace App\Http\Controllers;

use App\Models\Line;
use App\Models\Station;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'lines' => Line::withCount('stations')->orderBy('sort_order')->get(),
            'stationCount' => Station::count(),
        ]);
    }
}
