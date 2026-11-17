<?php

namespace App\Services\Panel;

final readonly class DeletionResult
{
    public function __construct(
        public bool $detached,
        public bool $deleted,
    ) {}
}
