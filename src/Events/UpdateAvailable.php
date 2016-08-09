<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;

/**
 * UpdateFailed.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class UpdateAvailable
{
    /**
     * @var string
     */
    protected $eventName = 'Update available';

    /**
     * @var SourceRepositoryTypeContract
     */
    protected $sourceRepository;

    /**
     * UpdateFailed constructor.
     *
     * @param SourceRepositoryTypeContract $sourceRepository
     */
    public function __construct(SourceRepositoryTypeContract $sourceRepository)
    {
        $this->sourceRepository = $sourceRepository;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->eventName;
    }

    /**
     * Get the new version.
     *
     * @param string $prepend
     * @param string $append
     *
     * @return string
     */
    public function getVersionAvailable($prepend = '', $append = '')
    {
        return $this->sourceRepository->getVersionAvailable($prepend, $append);
    }
}
