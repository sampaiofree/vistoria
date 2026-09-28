<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Support\Facades\Cache;

final class OrganizationStorageUsage
{
    private const TTL_HOURS = 8;

    private const LOCK_SECONDS = 300;

    public function __construct(private readonly OrganizationStorageScanner $scanner) {}

    /** @return array<string, mixed>|null Null means another request is already calculating. */
    public function get(int $organizationId): ?array
    {
        $cacheKey = 'organization-storage-usage:v1:'.$organizationId;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $lock = Cache::lock($cacheKey.':lock', self::LOCK_SECONDS);
        if (! $lock->get()) {
            return null;
        }

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            $result = $this->scanner->scan($organizationId);
            Cache::put($cacheKey, $result, now()->addHours(self::TTL_HOURS));

            return $result;
        } finally {
            $lock->release();
        }
    }
}
