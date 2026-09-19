<?php

declare(strict_types=1);

namespace App\Services\Classification;

final readonly class DefectClassificationDefinition
{
    public function __construct(
        public string $code,
        public string $name,
        public string $description,
        public string $color,
        public int $position,
        public int $severity_rank,
        public int $lower_limit,
        public int $upper_limit,
    ) {}

    public function contains(int $score): bool
    {
        return $this->lower_limit <= $score && $score <= $this->upper_limit;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
