<?php

namespace App\Providers;

use App\Infrastructure\Host\AllowlistedHostFileReader;
use App\Infrastructure\Host\HostFileReader;
use App\Infrastructure\Secrets\EnvelopeCipher;
use App\Infrastructure\Secrets\KeyCanary;
use App\Infrastructure\Secrets\MasterKey;
use App\Models\OperationJob;
use App\Models\Project;
use App\Models\Workspace;
use App\Policies\OperationJobPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\WorkspacePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MasterKey::class, fn (): MasterKey => MasterKey::fromConfig());
        $this->app->singleton(EnvelopeCipher::class);
        $this->app->singleton(KeyCanary::class);
        $this->app->bind(HostFileReader::class, AllowlistedHostFileReader::class);
    }

    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(12)->letters()->mixedCase()->numbers());

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(OperationJob::class, OperationJobPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
    }
}
