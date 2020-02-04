<?php declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\AbstractRepositoryType;

class AbstractRepositoryTypeTest extends TestCase
{
    /**
     * @dataProvider pathProvider
     * @param $storagePath
     * @param $releaseName
     */
    public function testCreateReleaseFolder($storagePath, $releaseName)
    {
        $dir = $storagePath.'/'.$releaseName;
        $this->assertTrue(mkdir($dir), 'Release folder ['.$dir.'] created.');
        $this->assertFileExists($dir, 'Release folder ['.$dir.'] exists.');
        $this->assertTrue(rmdir($dir), 'Release folder ['.$dir.'] deleted.');
        $this->assertFileNotExists($dir, 'Release folder ['.$dir.'] does not exist.');
    }

    public function testFilesAllExcluded()
    {
        /** @var AbstractRepositoryType $mock */
        $mock = $this->getMockBuilder(AbstractRepositoryType::class)->getMock();
        $mock->method('isDirectoryExcluded')->willReturn(true);
        $this->assertTrue($mock->isDirectoryExcluded(['a', 'b'], ['a']));
        $this->assertTrue($mock->isDirectoryExcluded(['a', 'b'], ['a', 'b']));
        $this->assertTrue($mock->isDirectoryExcluded(['a', 'b'], ['a', 'b', 'c']));
    }

    public function testFilesNotAllExcluded()
    {
        /** @var AbstractRepositoryType $mock */
        $mock = $this->getMockBuilder(AbstractRepositoryType::class)->getMock();
        $mock->method('isDirectoryExcluded')->willReturn(false);
        $this->assertFalse($mock->isDirectoryExcluded(['a', 'b', 'c'], ['c']));
        $this->assertFalse($mock->isDirectoryExcluded(['a', 'b', 'c'], ['a', 'c']));
    }

    public function pathProvider()
    {
        return [
            ['/tmp', '1'],
            ['/tmp', '1.1'],
            ['/tmp', '1.2'],
            ['/tmp', 'v1.2'],
        ];
    }
}
