<?php

declare(strict_types=1);

namespace Codedge\Updater;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Traits\SupportPrivateAccessToken;
use Codedge\Updater\Traits\UseVersionFile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;

/**
 * SourceRepository.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
final class SourceRepository implements SourceRepositoryTypeContract
{
    use UseVersionFile;
    use SupportPrivateAccessToken;

    protected SourceRepositoryTypeContract $sourceRepository;
    protected UpdateExecutor $updateExecutor;

    public function __construct(SourceRepositoryTypeContract $sourceRepository, UpdateExecutor $updateExecutor)
    {
        $this->sourceRepository = $sourceRepository;
        $this->updateExecutor = $updateExecutor;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     */
    public function fetch(string $version = ''): Release
    {
        $version = $version ?: $this->getVersionAvailable();

        return $this->sourceRepository->fetch($version);
    }

    /**
     * @throws \Exception
     */
    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
    }

    public function getReleases(): Response
    {
        return $this->sourceRepository->getReleases();
    }

    /**
     * Check repository if a newer version than the installed one is available.
     */
    public function isNewVersionAvailable(string $currentVersion = ''): bool
    {
        return $this->sourceRepository->isNewVersionAvailable($currentVersion);
    }

    /*
     * Get the version that is currently installed.
     * Example: 1.1.0 or v1.1.0 or "1.1.0 version".
     *
     */
    public function getVersionInstalled(string $prepend = '', string $append = ''): string
    {
        return $this->sourceRepository->getVersionInstalled($prepend, $append); //@phpstan-ignore-line
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     */
    public function getVersionAvailable(string $prepend = '', string $append = ''): string
    {
        return $this->sourceRepository->getVersionAvailable($prepend, $append);
    }

    /**
     * Run pre update artisan commands from config.
     */
    public function preUpdateArtisanCommands(): int
    {
        $commands = collect(config('self-update.artisan_commands.pre_update'));

        $commands->each(function ($commandParams, $commandKey) {
            Artisan::call($commandKey, $commandParams['params']);
        });

        return $commands->count();
    }

    /**
     * Run post update artisan commands from config.
     */
    public function postUpdateArtisanCommands(): int
    {
        $commands = collect(config('self-update.artisan_commands.post_update'));

        $commands->each(function ($commandParams, $commandKey) {
            Artisan::call($commandKey, $commandParams['params']);
        });

        return $commands->count();
    }
}
