<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Infrastructure\Executor\ExecutionGate;
use App\Infrastructure\Operations\HostExecutionDenied;
use App\Infrastructure\Operations\LocalOperationProcess;
use App\Infrastructure\Operations\OperationCatalog;
use App\Infrastructure\Operations\OperationDispatcher;
use App\Infrastructure\Operations\OperationInputGuard;
use App\Infrastructure\Operations\OperationRejected;
use App\Infrastructure\Operations\OperationResultParser;
use Tests\TestCase;

class OperationsEngineTest extends TestCase
{
    private const EXECUTION_ID = '018f3a2e-7c3a-7b2a-8c1d-123456789abc';

    public function test_catalog_registers_real_scripts_and_keeps_dangerous_names_out(): void
    {
        $catalog = app(OperationCatalog::class);

        $this->assertGreaterThanOrEqual(121, count($catalog->names()));
        $this->assertFalse($catalog->has('firewall.flush'));
        $this->assertFalse($catalog->has('ssh.stop'));
        $this->assertSame('project', $catalog->get('automation.custom_script')->privilege);
        $this->assertFalse($catalog->get('host.facts')->mutating);
        $this->assertTrue($catalog->get('host.reboot_request')->mutating);
        $this->assertSame(30, $catalog->get('host.facts')->timeoutSeconds);
    }

    public function test_dispatcher_rejects_a_secret_key_before_the_closed_gate(): void
    {
        try {
            app(OperationDispatcher::class)->dispatch('host.facts', [
                'execution_id' => self::EXECUTION_ID,
                'password' => 'not-a-real-secret',
            ]);
            $this->fail('A secret key reached the gate.');
        } catch (OperationRejected $exception) {
            $this->assertSame('SECRET_IN_INPUT', $exception->errorCode);
        }
    }

    public function test_dispatcher_rejects_a_private_key_marker(): void
    {
        try {
            app(OperationDispatcher::class)->dispatch('ssh.install_public_key', [
                'execution_id' => self::EXECUTION_ID,
                'public_key' => '-----BEGIN OPENSSH PRIVATE KEY-----',
            ]);
            $this->fail('A private key marker was accepted.');
        } catch (OperationRejected $exception) {
            $this->assertSame('PRIVATE_KEY_REJECTED', $exception->errorCode);
        }
    }

    public function test_dispatcher_does_not_run_a_host_command_while_execution_is_disabled(): void
    {
        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());

        try {
            app(OperationDispatcher::class)->dispatch('host.facts', [
                'execution_id' => self::EXECUTION_ID,
            ]);
            $this->fail('The dispatcher returned a host result.');
        } catch (HostExecutionDenied $exception) {
            $this->assertSame('EXECUTION_DISABLED', $exception->errorCode);
            $this->assertStringNotContainsString('succeeded', $exception->getMessage());
        }
    }

    public function test_parser_keeps_preview_separate_and_redacts_diagnostics(): void
    {
        $parser = app(OperationResultParser::class);
        $result = $parser->parse(
            '{"schema_version":"1.0","operation":"service.reload","operation_version":"1","execution_id":"'.self::EXECUTION_ID.'","status":"preview","error_code":null,"observed_state":{},"plan":{"action":"reload"},"mutated":false}',
            'token=abc',
        );

        $this->assertSame('preview', $result->status);
        $this->assertFalse($result->mutated);
        $this->assertStringNotContainsString('abc', $result->diagnostics);

        try {
            $parser->parse('{"schema_version":"1.0","operation":"service.reload","operation_version":"1","execution_id":"'.self::EXECUTION_ID.'","status":"failed","error_code":null,"observed_state":{"password":"hidden"},"plan":{},"mutated":false}');
            $this->fail('A secret output key was accepted.');
        } catch (OperationRejected $exception) {
            $this->assertSame('SECRET_IN_OUTPUT', $exception->errorCode);
        }
    }

    public function test_read_only_facts_parse_without_opening_the_execution_gate(): void
    {
        if (! is_executable('/usr/bin/bash') && ! is_executable('/bin/bash')) {
            $this->markTestSkipped('This PHP runtime cannot see bash. The host process check lives in infrastructure/operations/tests/run.sh.');
        }

        $directory = sys_get_temp_dir().'/rh-os-'.bin2hex(random_bytes(4));
        mkdir($directory);
        file_put_contents($directory.'/os-release', "NAME=\"Ubuntu\"\nID=ubuntu\nVERSION_ID=\"24.04\"\nVERSION_CODENAME=noble\n");

        try {
            $result = app(LocalOperationProcess::class)->run('host.facts', [
                'execution_id' => self::EXECUTION_ID,
            ], [
                'RAHANDAZ_OPS_OS_RELEASE' => $directory.'/os-release',
            ]);
        } finally {
            @unlink($directory.'/os-release');
            @rmdir($directory);
        }

        $this->assertSame('succeeded', $result->status);
        $this->assertSame('untested', $result->observedState['compatibility'] ?? null);
        $this->assertTrue($result->observedState['recognized'] ?? false);
        $this->assertFalse($result->mutated);
        $this->assertNull($result->errorCode);
        $this->assertFalse(app(ExecutionGate::class)->allowsHostMutation());
    }

    public function test_process_rejects_a_non_executable_dispatcher_before_parsing_output(): void
    {
        $directory = sys_get_temp_dir().'/rh-executable-'.bin2hex(random_bytes(4));
        mkdir($directory.'/bin', 0700, true);
        mkdir($directory.'/contracts', 0700);
        $binary = $directory.'/bin/rahandaz-ops';
        $registry = $directory.'/contracts/registry.json';
        file_put_contents($binary, "#!/usr/bin/env bash\nexit 99\n");
        chmod($binary, 0600);
        file_put_contents($registry, json_encode([
            'schema_version' => '1.0',
            'operations' => [[
                'name' => 'host.facts', 'version' => '1', 'privilege' => 'read',
                'timeout_seconds' => 30, 'mutating' => false,
                'idempotency' => 'read', 'script' => 'bin/rahandaz-ops',
            ]],
        ], JSON_THROW_ON_ERROR));

        try {
            $process = new LocalOperationProcess(
                new OperationCatalog($registry),
                app(OperationInputGuard::class),
                app(OperationResultParser::class),
            );
            $this->expectException(OperationRejected::class);
            $this->expectExceptionMessage('Operations dispatcher is not an executable regular file.');
            $process->run('host.facts', ['execution_id' => self::EXECUTION_ID]);
        } finally {
            unlink($binary);
            unlink($registry);
            rmdir($directory.'/bin');
            rmdir($directory.'/contracts');
            rmdir($directory);
        }
    }

    public function test_process_rejects_an_environment_key_outside_the_allowlist(): void
    {
        try {
            app(LocalOperationProcess::class)->run('host.facts', [
                'execution_id' => self::EXECUTION_ID,
            ], [
                'SECRET' => 'not-used',
            ]);
            $this->fail('An extra environment key was accepted.');
        } catch (OperationRejected $exception) {
            $this->assertSame('INVALID_INPUT', $exception->errorCode);
        }
    }
}
