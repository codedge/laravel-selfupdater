<?php

namespace Codedge\Updater\Tests;


/**
 * AbstractRepositoryTypeTest.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
abstract class AbstractRepositoryTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testUnzipArchive()
    {
        $mock = $this->getMockForAbstractClass('Codedge\Updater\AbstractRepositoryType');
    }
}