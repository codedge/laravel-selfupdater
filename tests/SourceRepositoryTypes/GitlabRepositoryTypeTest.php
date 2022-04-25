<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GitlabRepositoryType;
use Codedge\Updater\Tests\TestCase;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class GitlabRepositoryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_instantiate(): void
    {
        $this->assertInstanceOf(GitlabRepositoryType::class, resolve(GitlabRepositoryType::class));
    }

    /** @test */
    public function it_can_run_update(): void
    {
        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        /** @var Release $release */
        $release = resolve(Release::class);
        $release->setStoragePath((string) config('self-update.repository_types.gitlab.download_path'))
                ->setVersion('1.0')
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('some-local-file')
                ->download($this->getMockedClient([$this->getResponse200ZipFile()]));
        $release->extract();

        Event::fake();

        $this->assertTrue($gitlab->update($release));

        Event::assertDispatched(UpdateSucceeded::class, 1);
        Event::assertDispatched(UpdateSucceeded::class, function (UpdateSucceeded $e) use ($release) {
            return $e->getVersionUpdatedTo() === $release->getVersion();
        });
    }

    /** @test */
    public function it_can_get_the_version_installed(): void
    {
        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $this->assertEmpty($gitlab->getVersionInstalled());

        config(['self-update.version_installed' => '1.0']);
        $this->assertEquals('1.0', $gitlab->getVersionInstalled());
    }

    /** @test */
    public function it_cannot_get_new_version_available_and_fails_with_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $gitlab->isNewVersionAvailable();
    }

    /** @test */
    public function it_can_get_new_version_available_without_version_file(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('gitlab'),
            $this->getResponse200Type('gitlab'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $gitlab->deleteVersionFile();

        $gitlab->setAccessToken('123');

        Event::fake();

        $this->assertFalse($gitlab->isNewVersionAvailable('2.7'));
        $this->assertTrue($gitlab->isNewVersionAvailable('0.8'));

        Event::assertDispatched(UpdateAvailable::class, 1);
        Event::assertDispatched(UpdateAvailable::class, function (UpdateAvailable $e) use ($gitlab) {
            return $e->getVersionAvailable() === $gitlab->getVersionAvailable();
        });
    }

    /** @test */
    public function it_cannot_fetch_releases_because_there_is_no_release(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('gitlab'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        $this->assertInstanceOf(Release::class, $gitlab->fetch());

        $this->expectException(Exception::class);
        $this->assertInstanceOf(Release::class, $gitlab->fetch());
    }

    /** @test */
    public function it_cannot_fetch_releases_because_there_is_no_release_with_access_token(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('gitlab'),
            $this->getResponse200ZipFile(),
            $this->getResponse200Type('gitlab'),
            $this->getResponse200Type('gitlab'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $gitlab->setAccessToken('123');

        $this->assertInstanceOf(Release::class, $gitlab->fetch());

        $this->expectException(Exception::class);
        $this->assertInstanceOf(Release::class, $gitlab->fetch());
    }

    /** @test */
    public function it_can_fetch_releases(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('gitlab'),
            $this->getResponse200ZipFile(),
            $this->getResponse200Type('gitlab'),
            $this->getResponse200Type('gitlab'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        $release = $gitlab->fetch();

        $this->assertInstanceOf(Release::class, $release);
    }
}
