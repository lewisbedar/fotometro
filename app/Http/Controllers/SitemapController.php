<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Line;
use App\Models\Photo;
use App\Models\Station;
use App\Models\User;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null],
        ])
            ->merge(Station::query()->where('is_active', true)->get(['slug', 'updated_at'])
                ->map(fn (Station $station) => ['loc' => route('stations.show', $station->slug), 'lastmod' => $station->updated_at]))
            ->merge(Line::query()->where('is_active', true)->get(['slug', 'updated_at'])
                ->map(fn (Line $line) => ['loc' => route('lines.show', $line->slug), 'lastmod' => $line->updated_at]))
            ->merge(Photo::query()->publiclyVisible()->get(['slug', 'updated_at'])
                ->map(fn (Photo $photo) => ['loc' => route('photos.show', $photo->slug), 'lastmod' => $photo->updated_at]))
            ->merge(User::query()->where('status', UserStatus::Approved)->whereNotNull('username')->get(['username', 'updated_at'])
                ->map(fn (User $user) => ['loc' => route('profiles.show', $user->username), 'lastmod' => $user->updated_at]));

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'text/xml');
    }
}
