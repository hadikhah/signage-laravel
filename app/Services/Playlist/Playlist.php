<?php

namespace App\Services\Playlist;

use App\Models\Monitor;

final readonly class Playlist
{
    /** @param PlaylistItem[] $items */
    public function __construct(
        public array $items,
        public ?Monitor $monitor,
        public bool $bypassAllContent,
        public bool $isMonitorContent,
        public string $dailyMessage,
    ) {}

    public static function empty(): self
    {
        return new self(
            items: [],
            monitor: null,
            bypassAllContent: false,
            isMonitorContent: false,
            dailyMessage: '',
        );
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
