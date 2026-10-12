<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

final class OperationInputGuard
{
    private const FORBIDDEN_KEY = '/password|secret|token|private_key|authorization|credential/i';

    private const UUID_V7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * @param  array<mixed>  $input
     */
    public function assert(array $input): void
    {
        $this->walk($input);
        $executionId = $input['execution_id'] ?? null;

        if (! is_string($executionId) || preg_match(self::UUID_V7, $executionId) !== 1) {
            throw new OperationRejected('MISSING_EXECUTION_ID', 'execution_id must be a UUID v7.');
        }
    }

    private function walk(mixed $value, ?string $key = null): void
    {
        if ($key !== null && preg_match(self::FORBIDDEN_KEY, $key) === 1) {
            throw new OperationRejected('SECRET_IN_INPUT', 'Input contains a forbidden secret key.');
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->walk($child, is_string($childKey) ? $childKey : null);
            }

            return;
        }

        if (is_string($value) && str_contains($value, 'PRIVATE KEY')) {
            throw new OperationRejected('PRIVATE_KEY_REJECTED', 'A private key was rejected.');
        }
    }
}
