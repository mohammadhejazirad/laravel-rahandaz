<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait AssertsPostgresConstraints
{
    protected function assertSqlstate(string $sqlstate, callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $exception) {
            $this->assertSame($sqlstate, $exception->errorInfo[0] ?? null);

            return;
        }

        $this->fail('Expected SQLSTATE '.$sqlstate.'.');
    }
}
