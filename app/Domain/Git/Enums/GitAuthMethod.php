<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

use App\Support\HasEnumLabel;

enum GitAuthMethod: string
{
    use HasEnumLabel;

    case Oauth = 'oauth';
    case PersonalAccessToken = 'personal_access_token';
    case GithubApp = 'github_app';
    case GitlabToken = 'gitlab_token';

    public function label(): string
    {
        return match ($this) {
            self::Oauth => 'OAuth',
            self::PersonalAccessToken => 'توکن دسترسی شخصی',
            self::GithubApp => 'GitHub App',
            self::GitlabToken => 'توکن GitLab',
        };
    }
}
