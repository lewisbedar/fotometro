<?php

namespace App\Support;

use App\Enums\BadgeTier;

final readonly class AwardedBadge
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public BadgeTier $tier,
        public ?string $background = null,
        public ?string $textColor = null,
    ) {}

    public function displayBackground(): string
    {
        return $this->background ?? $this->tier->color();
    }

    public function displayTextColor(): string
    {
        return $this->textColor ?? '#ffffff';
    }
}
