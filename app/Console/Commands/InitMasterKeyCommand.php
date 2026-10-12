<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Secrets\EnvelopeCipher;
use App\Infrastructure\Secrets\Exceptions\SecretFilePermissionException;
use App\Infrastructure\Secrets\KeyCanary;
use App\Infrastructure\Secrets\MasterKey;
use App\Infrastructure\Secrets\SecretFile;
use Illuminate\Console\Command;

class InitMasterKeyCommand extends Command
{
    protected $signature = 'rahandaz:keys:init {--local-only : Create a development master key} {--path= : Master key path}';

    protected $description = 'Create the Rahandaz master key when it does not already exist';

    public function handle(): int
    {
        if (! $this->option('local-only')) {
            $this->error('Refusing to create a master key without --local-only. Production keys belong to the installer.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('path') ?: config('rahandaz.master_key_file'));
        $canaryPath = (string) config('rahandaz.canary_file');

        if (is_file($path) && filesize($path) > 0) {
            $this->error('Refusing to overwrite an existing master key.');

            return self::FAILURE;
        }

        try {
            SecretFile::write($path, base64_encode(random_bytes(32))."\n");
            $canary = new KeyCanary(new EnvelopeCipher(new MasterKey($path)));
            $canary->write($canaryPath);
        } catch (SecretFilePermissionException $exception) {
            @unlink($path);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Master key created.');
        $this->line('path: '.$path);
        $this->line('The key was not printed.');

        return self::SUCCESS;
    }
}
