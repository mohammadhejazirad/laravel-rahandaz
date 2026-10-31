<?php

declare(strict_types=1);

namespace App\Domain\Git;

use DateTimeImmutable;
use Throwable;

final class RepositoryActivity
{
    /** @param array<string, mixed> $repository */
    public static function latest(array $repository): ?string
    {
        $latest = null;
        foreach (['pushed_at', 'updated_at', 'last_activity_at', 'created_at'] as $key) {
            $value = $repository[$key] ?? null;
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
                continue;
            }
            try {
                $date = new DateTimeImmutable($value);
                if (DateTimeImmutable::getLastErrors() !== false) {
                    continue;
                }
                if ($latest === null || $date > $latest) {
                    $latest = $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $latest?->format('Y-m-d\TH:i:s.uP');
    }
}
