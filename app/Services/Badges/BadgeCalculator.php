<?php

namespace App\Services\Badges;

use App\Enums\BadgeTier;
use App\Models\Line;
use App\Models\Photo;
use App\Models\User;
use App\Support\AwardedBadge;
use Illuminate\Support\Collection;

class BadgeCalculator
{
    /** @var array<int, BadgeTier> Photo-count threshold => tier, highest reached wins. */
    private const MILESTONE_TIERS = [
        10 => BadgeTier::Bronze,
        50 => BadgeTier::Argent,
        100 => BadgeTier::Or,
        250 => BadgeTier::Platine,
    ];

    /** @var array<int, BadgeTier> Months since approval => tier, highest reached wins. */
    private const LOYALTY_TIERS = [
        3 => BadgeTier::Bronze,
        6 => BadgeTier::Argent,
        12 => BadgeTier::Or,
        24 => BadgeTier::Platine,
    ];

    private const FAN_STATION_MIN_PHOTOS = 3;

    private const FAN_LINE_MIN_PHOTOS = 5;

    private const FAN_SHARE_THRESHOLD = 0.5;

    /** Caps how many "Ligne X couverte" badges show at once, so a user who
     *  covers many small lines doesn't flood their profile. */
    private const MAX_LINE_COVERAGE_BADGES = 3;

    /** Overall cap across every badge family, applied last. */
    private const MAX_TOTAL_BADGES = 6;

    /**
     * @return Collection<int, AwardedBadge>
     */
    public function forUser(User $user): Collection
    {
        $photos = Photo::query()
            ->publiclyVisible()
            ->where('user_id', $user->id)
            ->with('station.lines')
            ->get();

        return collect([
            $this->milestoneBadge($photos->count()),
            ...$this->lineCoverageBadges($photos),
            $this->stationFanBadge($photos),
            $this->lineFanBadge($photos),
            $this->loyaltyBadge($user),
        ])->filter()->values()->take(self::MAX_TOTAL_BADGES);
    }

    private function milestoneBadge(int $publishedCount): ?AwardedBadge
    {
        if ($publishedCount === 1) {
            return new AwardedBadge('milestone', 'Première photo', 'A publié sa première photo.', BadgeTier::Bronze);
        }

        $threshold = null;
        $tier = null;

        foreach (self::MILESTONE_TIERS as $milestone => $milestoneTier) {
            if ($publishedCount >= $milestone) {
                $threshold = $milestone;
                $tier = $milestoneTier;
            }
        }

        // Between the first photo and the first threshold (2-9 photos), no
        // milestone badge applies yet — showing "Première photo" again would
        // be misleading.
        if ($threshold === null) {
            return null;
        }

        return new AwardedBadge(
            'milestone',
            "{$threshold} photos publiées",
            "A publié au moins {$threshold} photos approuvées.",
            $tier,
        );
    }

    /**
     * @param  Collection<int, Photo>  $photos
     * @return array<int, AwardedBadge>
     */
    private function lineCoverageBadges(Collection $photos): array
    {
        $photographedStationIdsByLine = [];

        foreach ($photos as $photo) {
            $station = $photo->station;

            if (! $station) {
                continue;
            }

            foreach ($station->lines as $line) {
                $photographedStationIdsByLine[$line->id][$station->id] = true;
            }
        }

        return Line::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['stations' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->filter(function (Line $line) use ($photographedStationIdsByLine) {
                $activeStationIds = $line->stations->pluck('id');

                if ($activeStationIds->isEmpty()) {
                    return false;
                }

                $photographed = $photographedStationIdsByLine[$line->id] ?? [];

                return $activeStationIds->every(fn ($stationId) => isset($photographed[$stationId]));
            })
            ->take(self::MAX_LINE_COVERAGE_BADGES)
            ->map(fn (Line $line) => new AwardedBadge(
                "line-coverage-{$line->id}",
                "Ligne {$line->code} couverte",
                "A photographié toutes les stations actives de la ligne {$line->code}.",
                BadgeTier::Or,
                background: $line->color,
                textColor: $line->text_color,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function stationFanBadge(Collection $photos): ?AwardedBadge
    {
        if ($photos->isEmpty()) {
            return null;
        }

        $total = $photos->count();
        $sorted = $photos->groupBy('station_id')->sortByDesc(fn (Collection $group) => $group->count());
        $topGroup = $sorted->first();

        if (! $topGroup || $topGroup->count() < self::FAN_STATION_MIN_PHOTOS || ($topGroup->count() / $total) < self::FAN_SHARE_THRESHOLD) {
            return null;
        }

        $station = $topGroup->first()->station;

        if (! $station) {
            return null;
        }

        return new AwardedBadge(
            'fan-station',
            "Fan de la station {$station->name}",
            "Plus de la moitié de ses photos publiées ont été prises à {$station->name}.",
            BadgeTier::Argent,
        );
    }

    /**
     * @param  Collection<int, Photo>  $photos
     */
    private function lineFanBadge(Collection $photos): ?AwardedBadge
    {
        if ($photos->isEmpty()) {
            return null;
        }

        $total = $photos->count();

        $byLine = $photos
            ->flatMap(fn (Photo $photo) => ($photo->station?->lines ?? collect())
                ->map(fn (Line $line) => ['line' => $line]))
            ->groupBy(fn (array $pair) => $pair['line']->id);

        if ($byLine->isEmpty()) {
            return null;
        }

        $sorted = $byLine->sortByDesc(fn (Collection $group) => $group->count());
        $topGroup = $sorted->first();

        if (! $topGroup || $topGroup->count() < self::FAN_LINE_MIN_PHOTOS || ($topGroup->count() / $total) < self::FAN_SHARE_THRESHOLD) {
            return null;
        }

        $line = $topGroup->first()['line'];

        return new AwardedBadge(
            'fan-line',
            "Fan de la ligne {$line->code}",
            "Plus de la moitié de ses photos publiées desservent la ligne {$line->code}.",
            BadgeTier::Argent,
            background: $line->color,
            textColor: $line->text_color,
        );
    }

    private function loyaltyBadge(User $user): ?AwardedBadge
    {
        if (! $user->approved_at) {
            return null;
        }

        $months = (int) $user->approved_at->diffInMonths(now());

        $threshold = null;
        $tier = null;

        foreach (self::LOYALTY_TIERS as $loyaltyMonths => $loyaltyTier) {
            if ($months >= $loyaltyMonths) {
                $threshold = $loyaltyMonths;
                $tier = $loyaltyTier;
            }
        }

        if ($threshold === null) {
            return null;
        }

        $label = $threshold >= 12
            ? sprintf('Membre depuis %d an%s', intdiv($threshold, 12), intdiv($threshold, 12) > 1 ? 's' : '')
            : "Membre depuis {$threshold} mois";

        return new AwardedBadge('loyalty', $label, "Compte approuvé depuis au moins {$threshold} mois.", $tier);
    }
}
