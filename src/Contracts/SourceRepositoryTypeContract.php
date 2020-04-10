<?php

declare(strict_types=1);

namespace Codedge\Updater\Contracts;

use Codedge\Updater\Models\Release;

interface SourceRepositoryTypeContract
{
    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @return Release
     */
    public function fetch(string $version = ''): Release;

    /**
     * Perform the actual update process.
     *
     * @param Release $release
     *
     * @return bool
     */
    public function update(Release $release): bool;

    /**
     * Check repository if a newer version than the installed one is available.
     * Caution: v.1.1 compared to 1.1 is not the same. Check to actually compare correct version, including letters
     * before or after.
     *
     * @param string $currentVersion
     *
     * @return bool
     */
    public function isNewVersionAvailable(string $currentVersion = ''): bool;

    /**
     * Get the version that is currently installed.
     */
    public function getVersionInstalled(): string;

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     *
     * @return string
     */
    public function getVersionAvailable(string $prepend = '', string $append = ''): string;
}
