<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\AbstractRepositoryType;
use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Exceptions\InvalidRepositoryException;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Traits\SupportPrivateAccessToken;
use Codedge\Updater\Traits\UseVersionFile;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

/**
 * GithubRepositoryType.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class GithubRepositoryType extends AbstractRepositoryType
{
    use UseVersionFile, SupportPrivateAccessToken;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Github constructor.
     *
     * @param array  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function create(): GithubRepositoryTypeContract
    {
        $this->checkValidRepository();

        if ($this->useBranchForVersions()) {
            return resolve(GithubBranchType::class);
        }

        return resolve(GithubTagType::class);
    }

    public function update(string $version = ''): bool
    {
        $this->setPathToUpdate(base_path(), config('self-update.exclude_folders'));

        if ($this->hasCorrectPermissionForUpdate()) {
            if (! empty($version)) {
                $sourcePath = $this->storagePath.$version;
            } else {
                $sourcePath = File::directories($this->storagePath)[0];
            }

            // Move all directories first
            collect((new Finder())->in($sourcePath)
                                  ->exclude(config('self-update.exclude_folders'))
                                  ->directories()
                                  ->sort(function ($a, $b) {
                                      return strlen($b->getRealpath()) - strlen($a->getRealpath());
                                  }))->each(function (/** @var \SplFileInfo $directory */ $directory) {
                                      if (! $this->isDirectoryExcluded(
                    File::directories($directory->getRealPath()), config('self-update.exclude_folders'))
                ) {
                                          File::copyDirectory(
                        $directory->getRealPath(),
                        base_path($directory->getRelativePath()).DIRECTORY_SEPARATOR.$directory->getBasename()
                    );
                                      }

                                      File::deleteDirectory($directory->getRealPath());
                                  });

            // Now move all the files left in the main directory
            collect(File::allFiles($sourcePath, true))->each(function ($file) { /* @var \SplFileInfo $file */
                if ($file->getRealPath()) {
                    File::copy($file->getRealPath(), base_path($file->getFilename()));
                }
            });

            File::deleteDirectory($sourcePath);
            $this->deleteVersionFile();
            event(new UpdateSucceeded($version));

            return true;
        }

        event(new UpdateFailed($this));

        return false;
    }

    protected function useBranchForVersions(): bool
    {
        return $this->config['use_branch'] !== '' ? true : false;
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
