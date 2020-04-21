<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\Models;

use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use org\bovigo\vfs\vfsStream;

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

        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_run_successfully(): void
    {
        $dir = (string) config('self-update.repository_types.github.download_path').'/update-dir';
        File::makeDirectory($dir, 0775, true, true);

        $client = $this->getMockedClient([
            $this->getResponse200ZipFile(),
        ]);

        $this->release->setVersion('release-1.2')
                      ->setStoragePath((string) config('self-update.repository_types.github.download_path'))
                      ->setRelease('release-1.2.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl('some/url/')
                      ->download($client);
        $this->release->extract();

        $updateExecutor = (new UpdateExecutor())->setBasePath($dir);

        Event::fake();

        $this->assertTrue($updateExecutor->run($this->release));
        $this->assertTrue(File::exists($dir.'/.some-hidden-file'));
        $this->assertTrue(File::exists($dir.'/outer-file.txt'));
        $this->assertTrue(File::exists($dir.'/folder1'));
        $this->assertEmpty(File::allFiles($dir.'/folder1'));
        $this->assertTrue(File::exists($dir.'/folder2'));
        $this->assertTrue(File::exists($dir.'/folder2/samplefile-in-folder2.txt'));
        $this->assertEquals(1, count(File::allFiles($dir.'/folder2')));
        $this->assertFalse(File::exists($dir.'/node_modules'));
        $this->assertFalse(File::exists($dir.'/__MACOSX'));
        $this->assertFalse(File::exists($dir.'/release-1.2'));
        $this->assertFalse(File::exists($dir.'/release-1.2.zip'));

        Event::assertDispatched(UpdateSucceeded::class, 1);
        Event::assertNotDispatched(UpdateFailed::class);
    }

    /** @test */
    public function it_can_run_and_fail(): void
    {
        vfsStream::newDirectory('updateDirectory')->at($this->vfs);
        vfsStream::newFile('sample-file', 0500)->at($this->vfs->getChild('updateDirectory'));

        $this->release->setUpdatePath($this->vfs->url().'/updateDirectory');

        Event::fake();

        $this->assertFalse((new UpdateExecutor())->setBasePath($this->vfs->url().'/updateDirectory')->run($this->release));

        Event::assertDispatched(UpdateFailed::class, 1);
        Event::assertNotDispatched(UpdateSucceeded::class);
    }
}
