<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

final class OperationCatalog
{
    /** @var array<string, OperationContract> */
    private array $operations;

    private string $root;

    public function __construct(?string $registryPath = null)
    {
        $path = $registryPath ?? base_path('infrastructure/operations/contracts/registry.json');
        $this->root = dirname($path, 2);
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ($decoded['schema_version'] ?? null) !== '1.0' || ! is_array($decoded['operations'] ?? null)) {
            throw new OperationRejected('INVALID_INPUT', 'Operation registry schema is not 1.0.');
        }

        $operations = [];

        foreach ($decoded['operations'] as $row) {
            if (! is_array($row)) {
                throw new OperationRejected('INVALID_INPUT', 'Operation registry row is not an object.');
            }

            $contract = new OperationContract(
                name: (string) ($row['name'] ?? ''),
                version: (string) ($row['version'] ?? ''),
                privilege: (string) ($row['privilege'] ?? ''),
                timeoutSeconds: (int) ($row['timeout_seconds'] ?? 0),
                lock: isset($row['lock']) ? (string) $row['lock'] : null,
                idempotency: (string) ($row['idempotency'] ?? ''),
                mutating: (bool) ($row['mutating'] ?? false),
                script: (string) ($row['script'] ?? ''),
            );
            $script = $this->root.'/'.$contract->script;

            if (! is_file($script) || is_link($script)) {
                throw new OperationRejected('UNKNOWN_OPERATION', 'Operation script is not a regular file.');
            }

            $operations[$contract->name] = $contract;
        }

        $this->operations = $operations;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function get(string $name): OperationContract
    {
        return $this->operations[$name] ?? throw new OperationRejected('UNKNOWN_OPERATION', 'Operation is not registered.');
    }

    public function has(string $name): bool
    {
        return isset($this->operations[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->operations);
    }
}
