<?php

namespace Codedge\Updater\Events;

use App\Events\Event;
use Codedge\Updater\Contracts\SourceRepositoryTypeContract;

/**
 * UpdateFailed.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class UpdateSucceeded extends Event
{
    /**
     * @var SourceRepositoryTypeContract
     */
    protected $sourceRepository;

    /**
     * @var  string
     */
    protected $eventName = 'Update succeeded';

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
     * Get the event name
     *
     * @return string
     */
    public function getName()
    {
        return $this->eventName;
    }
}
