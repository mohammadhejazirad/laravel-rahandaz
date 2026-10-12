<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Domain\Jobs\Data\RecordOperationData;
use App\Domain\Jobs\Exceptions\UnsafePlan;

final class PlanGuard
{
    private const FORBIDDEN_KEY = '/password|secret|token|private_key|authorization|credential/i';

    /**
     * @param  array<string, mixed>  $plan
     */
    public function assertSafe(array $plan): void
    {
        $this->walk($plan);
    }

    public function hash(RecordOperationData $data): string
    {
        $payload = [
            'actor_id' => $data->actorId,
            'mutates_host' => $data->mutatesHost,
            'plan' => $data->plan,
            'type' => $data->type,
            'workspace_id' => $data->workspaceId,
        ];

        return hash('sha256', $this->canonical($payload));
    }

    /**
     * @param  array<mixed>  $value
     */
    public function canonical(array $value): string
    {
        return json_encode($this->sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function walk(mixed $value): void
    {
        if (! is_array($value)) {
            if (is_string($value) && $this->valueLooksSensitive($value)) {
                throw new UnsafePlan('Plan contains a forbidden secret-like value.');
            }

            return;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match(self::FORBIDDEN_KEY, $key) === 1) {
                throw new UnsafePlan('Plan contains a forbidden key.');
            }

            $this->walk($item);
        }
    }

    private function valueLooksSensitive(string $value): bool
    {
        return preg_match('/Bearer\s+\S+/i', $value) === 1
            || preg_match('#https?://[^/\s:]+:[^/\s@]+@#i', $value) === 1;
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->sort(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        return $value;
    }
}
