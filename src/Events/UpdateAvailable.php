<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

/**
 * UpdateFailed.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class UpdateAvailable
{
    protected $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    /**
     * Get the new version.
     *
     * @return string
     */
    public function getVersionAvailable(): string
    {
        return $this->release->getVersion();
    }
}
