<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Exceptions\InvalidRepositoryException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Traits\SupportPrivateAccessToken;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;

/**
 * GithubRepositoryType.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class GithubRepositoryType
{
    use SupportPrivateAccessToken;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var UpdateExecutor
     */
    protected $updateExecutor;

    /**
     * Github constructor.
     *
     * @param array $config
     * @param UpdateExecutor $updateExecutor
     */
    public function __construct(array $config, UpdateExecutor $updateExecutor)
    {
        $this->config = $config;
        $this->updateExecutor = $updateExecutor;
    }

    public function create(): GithubRepositoryTypeContract
    {
        $this->checkValidRepository();

        if ($this->useBranchForVersions()) {
            return resolve(GithubBranchType::class);
        }

        return resolve(GithubTagType::class);
    }

    /**
     * @param Release $release
     *
     * @return bool
     * @throws \Exception
     */
    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
    }

    protected function useBranchForVersions(): bool
    {
        return $this->config['use_branch'] !== '';
    }

    protected function checkValidRepository(): void
    {
        if (empty($this->config['repository_vendor']) || empty($this->config['repository_name'])) {
            report(new InvalidRepositoryException());
        }
    }

    /**
     * Get the version that is currently installed.
     * Example: 1.1.0 or v1.1.0 or "1.1.0 version".
     *
     * @param string $prepend
     * @param string $append
     *
     * @return string
     */
    public function getVersionInstalled(string $prepend = '', string $append = ''): string
    {
        return $prepend.config('self-update.version_installed').$append;
    }
}
