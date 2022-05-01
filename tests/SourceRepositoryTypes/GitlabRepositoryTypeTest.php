<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Exceptions\VersionException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GitlabRepositoryType;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
                ->setDownloadUrl('https://gitlab.com/download/target');

        Event::fake();
        Http::fake([
            'gitlab.com/*' => $this->getResponse200ZipFile(),
        ]);
        $release->download();
        $release->extract();

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
        $this->expectException(VersionException::class);
        $this->expectExceptionMessage('Version installed not found.');

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $gitlab->isNewVersionAvailable();
    }

    /** @test */
    public function it_can_get_new_version_available_without_version_file(): void
    {
        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);
        $gitlab->deleteVersionFile();

        Event::fake();
        Http::fake([
            '*' => $this->getResponse200Type('gitlab'),
        ]);

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
        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        Http::fake([
            '*' => $this->getResponseEmpty(),
        ]);

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('No release found for version "latest version". Please check the repository you\'re pulling from');

        $this->assertInstanceOf(Release::class, $gitlab->fetch());
    }

    /** @test */
    public function it_can_fetch_releases(): void
    {
        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        Http::fakeSequence()
            ->pushResponse($this->getResponse200Type('gitlab'))
            ->pushResponse($this->getResponse200ZipFile())
            ->pushResponse($this->getResponse200Type('gitlab'));

        $release = $gitlab->fetch();

        $this->assertInstanceOf(Release::class, $release);
    }

    /** @test */
    public function it_can_get_specific_release_from_collection(): void
    {
        $items = [
            [
                'tag_name' => '1.3',
                'name'     => 'New version 1.3',
            ],
            [
                'tag_name' => '1.2',
                'name'     => 'New version 1.2',
            ],
        ];

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        $this->assertEquals($items[1], $gitlab->selectRelease(collect($items), '1.2'));
    }

    /** @test */
    public function it_takes_latest_release_if_no_other_found(): void
    {
        $items = [
            [
                'tag_name' => '1.3',
                'name'     => 'New version 1.3',
            ],
            [
                'tag_name' => '1.2',
                'name'     => 'New version 1.2',
            ],
        ];

        /** @var GitlabRepositoryType $gitlab */
        $gitlab = resolve(GitlabRepositoryType::class);

        Log::shouldReceive('info')->once()->with('No release for version "1.7" found. Selecting latest.');

        $this->assertEquals('1.3', $gitlab->selectRelease(collect($items), '1.7')['tag_name']);
    }
}
