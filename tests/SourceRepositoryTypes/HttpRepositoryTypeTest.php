<?php declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use Exception;

class HttpRepositoryTypeTest extends TestCase
{
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

        $release = resolve(Release::class);
        $release->setStoragePath('/tmp')
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('some-local-file')
                ->download($this->getMockedDownloadZipFileClient());
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
    public function it_can_fetch_http_releases(): void
    {
        /** @var HttpRepositoryType $http */
        $http = resolve(HttpRepositoryType::class);

        $this->assertInstanceOf(Release::class, $http->fetch());
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
}
