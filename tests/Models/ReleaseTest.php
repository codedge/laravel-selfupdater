<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\Models;

use Codedge\Updater\Models\Release;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class ReleaseTest extends TestCase
{
    protected Release $release;
    protected vfsStreamDirectory $vfs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->release = resolve(Release::class);
        $this->vfs = vfsStream::setup();

        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_get_release(): void
    {
        $releaseName = 'releaseName';
        $this->release->setRelease($releaseName);

        $this->assertEquals($releaseName, $this->release->getRelease());
    }

    /** @test */
    public function it_can_get_storage_path(): void
    {
        $this->assertNull($this->release->getStoragePath());

        $storagePath = Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).'tmp/releaseName.zip';
        $this->release->setStoragePath($storagePath);

        $this->assertEquals($storagePath, $this->release->getStoragePath());
    }

    /** @test */
    public function it_can_update_storage_path_when_having_release_name(): void
    {
        $releaseName = 'releaseName';
        $storagePathWithoutFilename = Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).'tmp';

        $this->release->setStoragePath($storagePathWithoutFilename);
        $this->assertEquals($storagePathWithoutFilename, $this->release->getStoragePath());

        $this->release->setRelease($releaseName)->updateStoragePath();
        $this->assertEquals(
            Str::finish($storagePathWithoutFilename, DIRECTORY_SEPARATOR).$releaseName,
            $this->release->getStoragePath()
        );
    }

    /** @test */
    public function it_should_not_update_storage_path_when_not_having_release_name(): void
    {
        $storagePath = Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).'tmp';

        $this->release->setStoragePath($storagePath);
        $this->assertEquals($storagePath, $this->release->getStoragePath());

        $this->release->updateStoragePath();
        $this->assertEquals($storagePath, $this->release->getStoragePath());
    }

    /** @test */
    public function it_can_get_update_path(): void
    {
        $this->assertNull($this->release->getUpdatePath());
    }

    /** @test */
    public function it_can_set_update_path_without_exclude_dirs(): void
    {
        $mainDirectory = '/tmp';
        $subDirectory = 'new-directory-inside';

        vfsStream::newDirectory($mainDirectory.'/'.$subDirectory)->at($this->vfs);
        $this->release->setUpdatePath(Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).$mainDirectory);

        foreach ($this->release->getUpdatePath()->directories() as $dir) {
            $this->assertEquals($dir->getPath(), $this->vfs->url().'/'.$mainDirectory);
            $this->assertEquals($subDirectory, $dir->getFilename());
            $this->assertEquals($subDirectory, $dir->getBasename());
        }
    }

    /** @test */
    public function it_can_get_version(): void
    {
        $this->assertNull($this->release->getVersion());
    }

    /** @test */
    public function it_can_set_version(): void
    {
        $this->release->setVersion('release-1.2.zip');
        $this->assertEquals('release-1.2.zip', $this->release->getVersion());
    }

    /** @test */
    public function it_can_get_download_url(): void
    {
        $this->assertNull($this->release->getDownloadUrl());
    }

    /** @test */
    public function it_can_set_download_url(): void
    {
        $this->release->setDownloadUrl('my-download-url');
        $this->assertEquals('my-download-url', $this->release->getDownloadUrl());
    }

    /** @test */
    public function it_cannot_extract_zip_and_fails_with_exception(): void
    {
        $this->release->setStoragePath('/tmp')->setRelease('release-test-99.zip')->updateStoragePath();
        $this->expectException(\Exception::class);
        $this->release->extract();
    }

    /** @test */
    public function it_can_extract_zip_to_storage_path(): void
    {
        $this->release->setStoragePath((string) config('self-update.repository_types.github.download_path'))->setRelease('release-test-1.2.zip')->updateStoragePath();

        $zip = new \ZipArchive();
        $res = $zip->open($this->release->getStoragePath(), \ZipArchive::CREATE);

        if ($res === true) {
            $zip->addFile(__DIR__.'/../Data/releases-branch.json');
            $zip->close();

            $this->assertTrue($this->release->extract());
        } else {
            var_dump($res);
            exit('Cannot open zip.');
        }
    }

    /** @test */
    public function it_cannot_download_and_fails_with_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->release->download();
    }

    /** @test */
    public function it_can_download(): void
    {
        $this->release->setDownloadUrl('https://github.com/some-file')
                      ->setStoragePath((string) config('self-update.repository_types.github.download_path'))
                      ->setRelease('release-1.0.zip')
                      ->updateStoragePath();

        Http::fake([
            'https://github.com/*' => $this->getResponse200Type('tag'),
        ]);

        $response = $this->release->download();

        $this->assertEquals(200, $response->status());
        $this->assertFileExists($this->release->getStoragePath());
    }

    /** @test */
    public function it_checks_source_is_not_fetched(): void
    {
        $this->release->setStoragePath($this->vfs->url().'release-1.2.zip');

        $this->assertFalse($this->release->isSourceAlreadyFetched());
    }

    /** @test */
    public function it_checks_source_is_already_fetched_but_not_extracted(): void
    {
        $file = 'release-1.2.zip';
        $storagePath = Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).$file;
        vfsStream::newFile($file)->at($this->vfs);

        $this->release->setStoragePath($storagePath);

        $this->assertTrue($this->release->isSourceAlreadyFetched());
    }

    /** @test */
    public function it_checks_source_is_already_extracted_and_directory_still_present(): void
    {
        vfsStream::newDirectory('release-1.2')->at($this->vfs);
        $this->release->setStoragePath(Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).'release-1.2.zip');

        $this->assertTrue($this->release->isSourceAlreadyFetched());
    }

    /** @test */
    public function it_checks_source_is_already_extracted_and_directory_deleted(): void
    {
        vfsStream::newDirectory('release-1.2')->at($this->vfs);
        $this->release->setStoragePath(Str::finish($this->vfs->url(), DIRECTORY_SEPARATOR).'release-1.2.zip');
        $this->vfs->getChild('release-1.2.zip')->rename('removed');

        $this->assertTrue($this->release->isSourceAlreadyFetched());
    }
}
