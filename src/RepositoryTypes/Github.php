<?php

namespace Codedge\Updater\RepositoryTypes;

use Codedge\Updater\Contracts\RepositoryType;

/**
 * Github.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class Github implements RepositoryType
{
    public function getLatestVersion($prepend = '', $append = '')
    {
    }

    public function downloadLatestSource()
    {
        // TODO: Implement downloadLatestSource() method.
    }

    protected function download($version = '')
    {
    }
}
