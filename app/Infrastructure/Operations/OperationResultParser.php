<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use App\Infrastructure\Secrets\Redactor;

final class OperationResultParser
{
    private const FORBIDDEN_KEY = '/password|secret|token|private_key|authorization|credential/i';

    public function __construct(private readonly Redactor $redactor) {}

    public function parse(string $stdout, string $stderr = ''): OperationResult
    {
        try {
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new OperationRejected('INVALID_INPUT', 'Operation stdout was not one JSON object.');
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation stdout was not one JSON object.');
        }

        $this->scan($decoded);
        $status = $decoded['status'] ?? null;

        if (! in_array($status, ['preview', 'succeeded', 'failed'], true)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation status is not in the contract.');
        }

        $schemaVersion = $decoded['schema_version'] ?? null;
        $operation = $decoded['operation'] ?? null;
        $executionId = $decoded['execution_id'] ?? null;

        if ($schemaVersion !== '1.0' || ! is_string($operation) || ! is_string($executionId)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation result is missing contract fields.');
        }

        $errorCode = $decoded['error_code'] ?? null;

        if ($errorCode !== null && ! is_string($errorCode)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation error_code must be a string or null.');
        }

        return new OperationResult(
            schemaVersion: '1.0',
            operation: $operation,
            operationVersion: (string) ($decoded['operation_version'] ?? ''),
            executionId: $executionId,
            status: $status,
            errorCode: $errorCode,
            observedState: is_array($decoded['observed_state'] ?? null) ? $decoded['observed_state'] : [],
            plan: is_array($decoded['plan'] ?? null) ? $decoded['plan'] : [],
            mutated: ($decoded['mutated'] ?? false) === true,
            diagnostics: $this->redactor->redact($stderr),
        );
    }

    private function scan(mixed $value, ?string $key = null): void
    {
        if ($key !== null && preg_match(self::FORBIDDEN_KEY, $key) === 1) {
            throw new OperationRejected('SECRET_IN_OUTPUT', 'Operation output contains a forbidden secret key.');
        }

        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->scan($child, is_string($childKey) ? $childKey : null);
            }

            return;
        }

        if (is_string($value) && str_contains($value, 'PRIVATE KEY')) {
            throw new OperationRejected('PRIVATE_KEY_REJECTED', 'Operation output contained a private key.');
        }
    }
}
