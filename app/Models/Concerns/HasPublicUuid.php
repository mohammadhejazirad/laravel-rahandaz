<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Identifiers;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Public UUIDv7 identifier. The primary key stays the bigint column id.
 *
 * Model events do not run for query-builder or bulk inserts. The database
 * trigger rahandaz_guard_public_uuid rejects a missing, non-v7, or changed uuid.
 * A trusted importer may assign a valid UUIDv7 before the first save. Public
 * requests cannot: uuid is not fillable.
 */
trait HasPublicUuid
{
    public function initializeHasPublicUuid(): void
    {
        $this->mergeGuarded(['id', 'uuid']);
    }

    public function getRouteKeyName(): string
    {
        $declared = static::resolveClassAttribute(RouteKey::class, 'key');

        return is_string($declared) && $declared !== '' ? $declared : 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'uuid' && ! Identifiers::isUuidV7((string) $value)) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }

    protected static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            $uuid = $model->getAttribute('uuid');

            if ($uuid === null || $uuid === '') {
                $model->setAttribute('uuid', Identifiers::uuid7());

                return;
            }

            if (! Identifiers::isUuidV7((string) $uuid)) {
                throw new InvalidArgumentException('Public uuid must be UUIDv7.');
            }
        });

        static::replicating(function (Model $model): void {
            $model->setAttribute('uuid', null);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('uuid')) {
                throw new InvalidArgumentException('Public uuid cannot be changed.');
            }
        });
    }
}
