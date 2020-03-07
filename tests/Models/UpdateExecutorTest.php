<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\Models;

use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\File;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\visitor\vfsStreamPrintVisitor;

class UpdateExecutorTest extends TestCase
{
    /**
     * @var Release
     */
    protected $release;

    /**
     * @var
     */
    protected $vfs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->release = resolve(Release::class);

        $this->vfs = vfsStream::setup('root');
    }

    /** @test */
    public function it_can_run_successfully(): void
    {
        File::makeDirectory('/tmp/update-dir', 0775, false, true);
        File::makeDirectory('/tmp/update-dir/folder1', 0755, true, true);
        File::makeDirectory('/tmp/update-dir/folder2', 0755, true, true);

        touch('/tmp/update-dir/folder1/file-a.php');
        touch('/tmp/update-dir/folder1/file-b.php');

        $this->release->setUpdatePath('/tmp/update-dir');

        $updateExecutor = new UpdateExecutor();
        $this->assertTrue($updateExecutor->setUseBasePath(false)->run($this->release));

    }

    /** @test */
    public function it_can_run_and_fail(): void
    {
        vfsStream::newDirectory('updateDirectory')->at($this->vfs);
        vfsStream::newFile('sample-file', 0500)->at($this->vfs->getChild('updateDirectory'));

        $this->release->setUpdatePath($this->vfs->url() . '/updateDirectory');

        $this->assertFalse((new UpdateExecutor())->run($this->release));
    }
}
