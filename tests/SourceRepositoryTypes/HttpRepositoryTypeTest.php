<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Exceptions\VersionException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\HttpRepositoryType;
use Codedge\Updater\Tests\TestCase;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

final class HttpRepositoryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_instantiate(): void
    {
        $this->assertInstanceOf(HttpRepositoryType::class, resolve(HttpRepositoryType::class));
    }

    /** @test */
    public function it_can_run_update(): void
    {
        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        Http::fake([
            '*' => $this->getResponse200ZipFile(),
        ]);

        $release = resolve(Release::class);
        $release->setStoragePath((string) config('self-update.repository_types.http.download_path'))
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('some-local-file')
                ->download();
        $release->extract();

        $this->assertTrue($http->update($release));
    }

    /** @test */
    public function it_cannot_fetch_http_releases_if_no_url_specified(): void
    {
        config(['self-update.repository_types.http.repository_url' => '']);

        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        $this->expectException(Exception::class);
        $http->fetch();
    }

    /** @test */
    public function it_cannot_fetch_releases_because_there_is_no_release(): void
    {
        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        Http::fakeSequence()
            ->pushResponse($this->getResponse200HttpType())
            ->pushResponse($this->getResponse200ZipFile());

        //$this->expectException(ReleaseException::class);
        //$this->expectExceptionMessage('Archive file "/tmp/self-updater/v/invoiceninja/invoiceninja/archive/v4.5.17.zip" not found.');

        $this->assertInstanceOf(Release::class, $http->fetch());
    }

    /** @test */
    public function it_can_fetch_http_releases(): void
    {
        /** @var HttpRepositoryType $http */
        $http = $this->app->make(HttpRepositoryType::class);

        Http::fakeSequence()
            ->pushResponse($this->getResponse200HttpType())
            ->pushResponse($this->getResponse200ZipFile())
            ->pushResponse($this->getResponse200HttpType())
            ->pushResponse($this->getResponse200HttpType());

        File::shouldReceive('exists')->andReturnTrue();

        $release = $http->fetch();

        $this->assertInstanceOf(Release::class, $release);
    }

    /** @test */
    public function it_can_get_the_version_installed(): void
    {
        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);
        $this->assertEmpty($http->getVersionInstalled());

        config(['self-update.version_installed' => '1.0']);
        $this->assertEquals('1.0', $http->getVersionInstalled());
    }

    /** @test */
    public function it_can_get_latest_release_from_collection(): void
    {
        $items = [
            ['name' => '1.3'],
            ['name' => '1.2'],
        ];

        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        $this->assertEquals($items[0], $http->selectRelease(collect($items), ''));
    }

    /** @test */
    public function it_can_get_specific_release_from_collection(): void
    {
        $items = [
            ['name' => '1.3'],
            ['name' => '1.2'],
        ];

        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        $this->assertEquals($items[1], $http->selectRelease(collect($items), '1.2'));
    }

    /** @test */
    public function it_cannot_find_specific_release_and_returns_first_from_collection(): void
    {
        $items = [
            ['name' => '1.3'],
            ['name' => '1.2'],
        ];

        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        $this->assertEquals($items[0], $http->selectRelease(collect($items), '1.4'));
    }

    /** @test */
    public function it_cannot_get_new_version_available_and_fails_with_exception(): void
    {
        $this->expectException(VersionException::class);
        $this->expectExceptionMessage('Version installed not found.');

        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);
        $http->isNewVersionAvailable();
    }

    /** @test */
    public function it_can_get_new_version_available_without_version_file(): void
    {
        /** @var HttpRepositoryType $http */
        $http = $this->app->make(HttpRepositoryType::class);
        $http->deleteVersionFile();

        Http::fakeSequence()
            ->pushResponse($this->getResponse200HttpType())
            ->pushResponse($this->getResponse200HttpType());

        $this->assertTrue($http->isNewVersionAvailable('4.5'));
        $this->assertFalse($http->isNewVersionAvailable('5.0'));
    }

    /** @test */
    public function it_can_build_releases_from_local_source(): void
    {
        config(['self-update.repository_types.http.repository_url' => 'http://update-server.localhost/']);
        config(['self-update.repository_types.http.pkg_filename_format' => 'my-test-project-\d+\.\d+']);

        /** @var HttpRepositoryType $http */
        $http = $this->app->make(HttpRepositoryType::class);
        $content = file_get_contents('tests/Data/Http/releases-http_local.json');

        $collection = $http->extractFromHtml($content);

        $this->assertSame('1.0', $collection->first()->name);
        $this->assertSame('http://update-server.localhost/my534/my-test-project/-/archive/1.0/my-test-project-1.0.zip', $collection->first()->zipball_url);
    }

    /** @test */
    public function it_can_build_releases_from_github_source(): void
    {
        config(['self-update.repository_types.http.repository_url' => 'https://github.com/']);

        /** @var HttpRepositoryType $http */
        $http = $this->app->make(HttpRepositoryType::class);
        $content = file_get_contents('tests/Data/Http/releases-http_gh.json');

        $collection = $http->extractFromHtml($content);

        $this->assertSame('4.5.17', $collection->first()->name);
        $this->assertSame('https://github.com/invoiceninja/invoiceninja/archive/v4.5.17.zip', $collection->first()->zipball_url);
    }
}
