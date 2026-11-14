<?php

namespace App\Services\Playlist;

use Carbon\CarbonInterface;

final readonly class PlaylistItem
{
    public function __construct(
        public int $contentId,
        public string $contentName,
        public ?int $userId,
        public ?string $userName,
        public int $contentType,
        public ?string $contentPath,
        public ?string $clientFileName,
        public ?CarbonInterface $fromDate,
        public ?CarbonInterface $toDate,
        public int $displayTime,
        public bool $active,
        public ?string $url,
        public ?string $urlUserName,
        public ?string $urlPassword,
        public ?bool $isAutoLogin,
    ) {}
}
