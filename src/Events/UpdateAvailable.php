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
class UpdateAvailable extends Event
{
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
}
