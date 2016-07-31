<?php

namespace Codedge\Updater;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;

/**
 * SourceRepository.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class SourceRepository implements SourceRepositoryTypeContract
{
    /**
     * @var SourceRepositoryTypeContract
     */
    protected $sourceRepository;

    /**
     * SourceRepository constructor.
     *
     * @param SourceRepositoryTypeContract $sourceRepository
     */
    public function __construct(SourceRepositoryTypeContract $sourceRepository)
    {
        $this->sourceRepository = $sourceRepository;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @return mixed
     */
    public function fetch($version = '')
    {
        return $this->sourceRepository->fetch($version);
    }

    /**
     * Perform the actual update process.
     *
     * @return bool
     */
    public function update() : bool
    {
        return $this->sourceRepository->update();
    }

    /**
     * Check repository if a newer version than the installed one is available.
     *
     * @param string $currentVersion
     *
     * @return bool
     */
    public function isNewVersionAvailable($currentVersion = '') : bool
    {
        return $this->sourceRepository->isNewVersionAvailable($currentVersion);
    }

    /**
     * Get the version that is currenly installed.
     * Example: 1.1.0 or v1.1.0 or "1.1.0 version".
     *
     * @param string $prepend
     * @param string $append
     *
     * @return string
     */
    public function getVersionInstalled($prepend = '', $append = '') : string
    {
        return $this->sourceRepository->getVersionInstalled($prepend, $append);
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     *
     * @return string
     */
    public function getVersionAvailable($prepend = '', $append = '') : string
    {
        return $this->sourceRepository->getVersionAvailable($prepend, $append);
    }
}
