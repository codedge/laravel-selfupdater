<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\Models;

use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use GuzzleHttp\Client;
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
        $dir = (string) config('self-update.repository_types.github.download_path') . '/update-dir';
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
        $this->assertTrue($updateExecutor->run($this->release));

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
