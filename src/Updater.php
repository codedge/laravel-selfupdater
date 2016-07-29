<?php

namespace Codedge\Updater;

use Illuminate\Config\Repository as Config;

/**
 * Updater.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class Updater
{
    protected $config;

    /**
     * Updater constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }
    
    
}