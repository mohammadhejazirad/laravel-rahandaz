<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Accounts\AccountBootstrapper;
use App\Infrastructure\Secrets\Exceptions\SecretFilePermissionException;
use App\Infrastructure\Secrets\SecretFile;
use Illuminate\Console\Command;

class BootstrapOwnerCommand extends Command
{
    protected $signature = 'rahandaz:bootstrap-owner {--credentials-file= : Where the initial password file is written}';

    protected $description = 'Create the initial panel owner when one does not exist';

    public function handle(AccountBootstrapper $bootstrapper): int
    {
        $path = (string) $this->option('credentials-file');

        if ($path === '') {
            $this->error('Pass --credentials-file. This command does not choose a secret path on its own.');

            return self::FAILURE;
        }

        if ($this->pathIsPublic($path)) {
            $this->error('Refusing to write credentials inside the public directory.');

            return self::FAILURE;
        }

        if ($bootstrapper->ownerExists()) {
            $this->info('Owner already exists. No credential file was written.');

            return self::SUCCESS;
        }

        if (is_file($path)) {
            $this->error('Refusing to overwrite an existing credentials file.');

            return self::FAILURE;
        }

        $created = $bootstrapper->createInitialOwner();

        if ($created === null) {
            $this->info('Owner already exists. No credential file was written.');

            return self::SUCCESS;
        }

        $body = implode("\n", [
            'Rahandaz initial panel owner',
            'username: '.$created['username'],
            'password: '.$created['password'],
            'Change this password at the first login.',
            '',
        ]);

        try {
            SecretFile::write($path, $body);
        } catch (SecretFilePermissionException $exception) {
            $this->error($exception->getMessage());
            $this->error('The owner row was created, but the credential file could not be stored safely.');

            return self::FAILURE;
        }

        $this->info('Owner created. Credentials written to: '.$path);
        $this->line('The password was not printed.');

        return self::SUCCESS;
    }

    private function pathIsPublic(string $path): bool
    {
        $public = realpath(public_path());
        $directory = dirname($path);
        $realDirectory = is_dir($directory) ? realpath($directory) : false;

        if ($public !== false && $realDirectory !== false && str_starts_with($realDirectory.DIRECTORY_SEPARATOR, $public.DIRECTORY_SEPARATOR)) {
            return true;
        }

        return str_contains($path, '/public/') || str_ends_with($path, '/public');
    }
}
