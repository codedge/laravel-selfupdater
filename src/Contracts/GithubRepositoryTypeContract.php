<?php

declare(strict_types=1);

namespace Codedge\Updater\Contracts;

interface GithubRepositoryTypeContract extends SourceRepositoryTypeContract
{
    const GITHUB_API_URL = 'https://api.github.com';
    const GITHUB_URL = 'https://github.com';
}
