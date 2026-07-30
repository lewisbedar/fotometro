<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\User;
use App\Services\Badges\BadgeCalculator;
use App\Services\Profiles\AvatarProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request, User $user, BadgeCalculator $badges): View
    {
        $user->loadMissing(['favoriteStation', 'favoriteLine']);

        $publishedPhotosQuery = Photo::query()->publiclyVisible()->where('user_id', $user->id);
        $isOwnProfile = $request->user()?->is($user) ?? false;
        $stationIds = (clone $publishedPhotosQuery)->pluck('station_id')->unique();

        return view('profiles.show', [
            'profileUser' => $user,
            'isOwnProfile' => $isOwnProfile,
            'badges' => $badges->forUser($user),
            'publishedPhotoCount' => (clone $publishedPhotosQuery)->count(),
            'stationCount' => $stationIds->count(),
            'lineCount' => DB::table('station_line')->whereIn('station_id', $stationIds)->pluck('line_id')->unique()->count(),
        ]);
    }

    public function updateBio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bio' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->forceFill(['bio' => $data['bio'] ?? null])->save();

        return redirect()->route('profiles.show', $request->user())->with('status', 'Bio mise à jour.');
    }

    public function updateAvatar(Request $request, AvatarProcessor $processor): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png,webp',
                'max:'.((int) config('fotometro.avatar.max_upload_mb', 8) * 1024),
            ],
        ]);

        $user = $request->user();
        $path = $processor->store($data['avatar'], $user);
        $user->forceFill(['avatar_path' => $path])->save();

        return redirect()->route('profiles.show', $user)->with('status', 'Photo de profil mise à jour.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return redirect()->route('profiles.show', $user)->with('status', 'Photo de profil supprimée.');
    }
}
