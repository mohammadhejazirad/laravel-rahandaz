<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait HasUuidV7
{
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    protected static function bootHasUuidV7(): void
    {
        static::creating(function (Model $model): void {
            $key = $model->getKeyName();
            $value = $model->getAttribute($key);

            if ($value === null || $value === '') {
                $model->setAttribute($key, Identifiers::uuid7());

                return;
            }

            if (! Identifiers::isUuidV7((string) $value)) {
                throw new InvalidArgumentException('Primary keys must be UUIDv7.');
            }
        });
    }
}
