<?php

declare(strict_types=1);

namespace App\Infrastructure\Operations;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Direct process adapter for an allowlisted operation.
 * The job runner must not call this while ExecutionGate is closed.
 */
final class LocalOperationProcess
{
    /** @var list<string> */
    private const ALLOWED_ENV = [
        'RAHANDAZ_OPS_APPLY',
        'RAHANDAZ_OPS_BIN_DIR',
        'RAHANDAZ_OPS_CATALOG',
        'RAHANDAZ_OPS_HELPER_CONFIRMED',
        'RAHANDAZ_OPS_HELPER_SOCKET',
        'RAHANDAZ_OPS_OS_RELEASE',
        'RAHANDAZ_OPS_PROJECT_CONFIRMED',
        'RAHANDAZ_OPS_PROJECT_UID',
        'RAHANDAZ_OPS_SANDBOX',
    ];

    public function __construct(
        private readonly OperationCatalog $catalog,
        private readonly OperationInputGuard $guard,
        private readonly OperationResultParser $parser,
    ) {}

    /**
     * @param  array<mixed>  $input
     * @param  array<string, string>  $extraEnv
     */
    public function run(string $operation, array $input, array $extraEnv = []): OperationResult
    {
        $contract = $this->catalog->get($operation);
        $this->guard->assert($input);
        $binary = $this->catalog->root().'/bin/rahandaz-ops';

        if (! is_file($binary) || is_link($binary)) {
            throw new OperationRejected('TOOL_MISSING', 'Operations dispatcher is not a regular file.');
        }

        $process = new Process(
            [$binary, $contract->name],
            $this->catalog->root(),
            $this->environment($extraEnv),
            json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            (float) $contract->timeoutSeconds,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new OperationRejected('TIMEOUT', 'Operation exceeded its contract timeout.');
        }

        $result = $this->parser->parse($process->getOutput(), $process->getErrorOutput());
        $failed = $result->status === 'failed';

        if ($process->isSuccessful() === $failed) {
            throw new OperationRejected('VERIFICATION_FAILED', 'Operation exit status disagreed with the JSON status.');
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $extraEnv
     * @return array<string, string>
     */
    private function environment(array $extraEnv): array
    {
        $env = [
            'PATH' => '/usr/bin:/bin:/usr/sbin:/sbin',
            'LANG' => 'C',
            'LC_ALL' => 'C',
            'HOME' => sys_get_temp_dir(),
            'TMPDIR' => sys_get_temp_dir(),
        ];

        foreach ($extraEnv as $key => $value) {
            if (! in_array($key, self::ALLOWED_ENV, true)) {
                throw new OperationRejected('INVALID_INPUT', 'Environment key is not allowlisted.');
            }

            $env[$key] = $value;
        }

        return $env;
    }
}
