<?php

declare(strict_types=1);

namespace App\Domain\Git\Enums;

enum GitAuthMethod: string
{
    case Oauth = 'oauth';
    case PersonalAccessToken = 'personal_access_token';
    case GithubApp = 'github_app';
    case GitlabToken = 'gitlab_token';
}
