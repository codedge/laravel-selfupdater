<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Exceptions\VersionException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GiteaRepositoryType;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GiteaRepositoryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_instantiate(): void
    {
        $this->assertInstanceOf(GiteaRepositoryType::class, resolve(GiteaRepositoryType::class));
    }

    /** @test */
    public function it_can_run_update(): void
    {
        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);

        /** @var Release $release */
        $release = resolve(Release::class);
        $release->setStoragePath((string) config('self-update.repository_types.gitea.download_path'))
                ->setVersion('1.0')
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('https://gitea.com/download/target');

        Event::fake();
        Http::fake([
            'gitea.com/*' => $this->getResponse200ZipFile(),
        ]);
        $release->download();
        $release->extract();

        $this->assertTrue($gitea->update($release));

        Event::assertDispatched(UpdateSucceeded::class, 1);
        Event::assertDispatched(UpdateSucceeded::class, function (UpdateSucceeded $e) use ($release) {
            return $e->getVersionUpdatedTo() === $release->getVersion();
        });
    }

    /** @test */
    public function it_can_get_the_version_installed(): void
    {
        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);
        $this->assertEmpty($gitea->getVersionInstalled());

        config(['self-update.version_installed' => '1.0']);
        $this->assertEquals('1.0', $gitea->getVersionInstalled());
    }

    /** @test */
    public function it_cannot_get_new_version_available_and_fails_with_exception(): void
    {
        $this->expectException(VersionException::class);
        $this->expectExceptionMessage('Version installed not found.');

        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);
        $gitea->isNewVersionAvailable();
    }

    /** @test */
    public function it_can_get_new_version_available_without_version_file(): void
    {
        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);
        $gitea->deleteVersionFile();

        Event::fake();
        Http::fake([
            '*' => $this->getResponse200Type('gitea'),
        ]);

        $this->assertFalse($gitea->isNewVersionAvailable('2.7'));
        $this->assertTrue($gitea->isNewVersionAvailable('0.0.1'));

        Event::assertDispatched(UpdateAvailable::class, 1);
        Event::assertDispatched(UpdateAvailable::class, function (UpdateAvailable $e) use ($gitea) {
            return $e->getVersionAvailable() === $gitea->getVersionAvailable();
        });
    }

    /** @test */
    public function it_cannot_fetch_releases_because_there_is_no_release(): void
    {
        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);

        Http::fake([
            '*' => $this->getResponseEmpty(),
        ]);

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('No release found for version "latest version". Please check the repository you\'re pulling from');

        $this->assertInstanceOf(Release::class, $gitea->fetch());
    }

    /** @test */
    public function it_can_fetch_releases(): void
    {
        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);

        Http::fakeSequence()
            ->pushResponse($this->getResponse200Type('gitea'))
            ->pushResponse($this->getResponse200ZipFile())
            ->pushResponse($this->getResponse200Type('gitea'));

        $release = $gitea->fetch();

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

        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);

        $this->assertEquals($items[1], $gitea->selectRelease(collect($items), '1.2'));
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

        /** @var GiteaRepositoryType $gitea */
        $gitea = resolve(GiteaRepositoryType::class);

        Log::shouldReceive('info')->once()->with('No release for version "1.7" found. Selecting latest.');

        $this->assertEquals('1.3', $gitea->selectRelease(collect($items), '1.7')['tag_name']);
    }
}
