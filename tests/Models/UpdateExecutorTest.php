<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\Models;

use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Tests\TestCase;
use GuzzleHttp\Client;
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

        $this->release->setStoragePath('/tmp')
                      ->setRelease('release-1.0.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl('some-local-file')
                      ->download($this->getMockedDownloadZipFileClient());
        $this->release->extract();

        $updateExecutor = new UpdateExecutor();
        $this->assertTrue($updateExecutor->setBasePath('/tmp/update-dir')->run($this->release));

    }

    /** @test */
    public function it_can_run_and_fail(): void
    {
        vfsStream::newDirectory('updateDirectory')->at($this->vfs);
        vfsStream::newFile('sample-file', 0500)->at($this->vfs->getChild('updateDirectory'));

        $this->release->setUpdatePath($this->vfs->url() . '/updateDirectory');

        $this->assertFalse((new UpdateExecutor())->setBasePath($this->vfs->url() . '/updateDirectory')->run($this->release));
    }
}
