<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Secrets\CredentialStore;
use App\Infrastructure\Secrets\EnvelopeCipher;
use App\Infrastructure\Secrets\KeyCanary;
use App\Infrastructure\Secrets\MasterKey;
use App\Models\Credential;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPanelAccount;
use Tests\TestCase;

class SecretsAndBootstrapTest extends TestCase
{
    use CreatesPanelAccount, RefreshDatabase;

    public function test_credentials_round_trip_without_storing_plaintext(): void
    {
        [$user, $workspace] = $this->panelAccount();
        $store = app(CredentialStore::class);
        $credential = $store->store($workspace, $user, 'github', 'ghp_test_secret_value');

        $this->assertSame('ghp_test_secret_value', $store->open($credential));
        $this->assertArrayNotHasKey('envelope', $credential->toArray());

        $row = DB::table('credentials')->where('id', $credential->id)->first();
        $encoded = json_encode($row, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('ghp_test_secret_value', $encoded);
        $this->assertNotSame('ghp_test_secret_value', $credential->fingerprint);
        $this->assertInstanceOf(Credential::class, $credential);
    }

    public function test_bootstrap_owner_writes_credentials_once_and_does_not_print_the_password(): void
    {
        $path = sys_get_temp_dir().'/rahandaz-owner-'.bin2hex(random_bytes(4)).'.txt';

        $this->assertSame(1, Artisan::call('rahandaz:bootstrap-owner', [
            '--credentials-file' => public_path('owner.txt'),
        ]));
        $this->assertSame(0, User::query()->count());

        $this->assertSame(0, Artisan::call('rahandaz:bootstrap-owner', [
            '--credentials-file' => $path,
        ]));
        $output = Artisan::output();
        $body = (string) file_get_contents($path);
        preg_match('/^password: (.+)$/m', $body, $matches);
        $password = $matches[1] ?? '';

        $this->assertNotSame('', $password);
        $this->assertStringNotContainsString($password, $output);
        $this->assertStringContainsString('The password was not printed.', $output);
        $this->assertSame(0600, fileperms($path) & 0777);
        $this->assertSame(1, Membership::query()->count());

        $this->assertSame(0, Artisan::call('rahandaz:bootstrap-owner', [
            '--credentials-file' => $path,
        ]));
        $this->assertStringContainsString('Owner already exists', Artisan::output());
        $this->assertSame(1, User::query()->count());

        @unlink($path);
    }

    public function test_master_key_init_refuses_overwrite_and_does_not_print_the_key(): void
    {
        $directory = sys_get_temp_dir().'/rahandaz-keys-'.bin2hex(random_bytes(4));
        mkdir($directory, 0700, true);
        config([
            'rahandaz.master_key_file' => $directory.'/master.key',
            'rahandaz.canary_file' => $directory.'/canary.json',
        ]);
        $this->forgetSecrets();

        $this->assertSame(1, Artisan::call('rahandaz:keys:init'));
        $this->assertFileDoesNotExist($directory.'/master.key');

        $this->assertSame(0, Artisan::call('rahandaz:keys:init', ['--local-only' => true]));
        $output = Artisan::output();
        $encoded = trim((string) file_get_contents($directory.'/master.key'));

        $this->assertStringNotContainsString($encoded, $output);
        $this->assertStringContainsString('The key was not printed.', $output);
        $this->assertSame(0600, fileperms($directory.'/master.key') & 0777);
        $this->assertTrue(app(KeyCanary::class)->verify($directory.'/canary.json'));

        $this->assertSame(1, Artisan::call('rahandaz:keys:init', ['--local-only' => true]));
    }

    public function test_doctor_fails_closed_when_the_master_key_is_missing(): void
    {
        $this->assertSame(0, Artisan::call('rahandaz:doctor'));
        $healthy = Artisan::output();

        $this->assertStringContainsString('master_key: ok', $healthy);
        $this->assertStringContainsString('database: ok', $healthy);
        $this->assertStringContainsString('redis_queue: ok', $healthy);
        $this->assertStringContainsString('redis_cache: ok', $healthy);
        $this->assertStringContainsString('execution_mode: ok disabled', $healthy);
        $this->assertStringNotContainsString(trim((string) file_get_contents((string) config('rahandaz.master_key_file'))), $healthy);

        config(['rahandaz.master_key_file' => '/tmp/rahandaz-missing-master.key']);
        $this->forgetSecrets();

        $this->assertSame(1, Artisan::call('rahandaz:doctor'));
        $failed = Artisan::output();
        $this->assertStringContainsString('master_key: fail', $failed);
        $this->assertStringContainsString('master_key_mode: fail', $failed);
    }

    private function forgetSecrets(): void
    {
        $this->app->forgetInstance(MasterKey::class);
        $this->app->forgetInstance(EnvelopeCipher::class);
        $this->app->forgetInstance(KeyCanary::class);
    }
}
