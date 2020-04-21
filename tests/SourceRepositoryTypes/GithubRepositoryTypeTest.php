<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

class GithubRepositoryTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetDownloadDir();
    }

    /** @test */
    public function it_can_instantiate(): void
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubTagType::class, $github);
    }

    /** @test */
    public function it_can_instantiate_branch_type(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubBranchType::class, $github);
    }

    /** @test */
    public function it_cannot_instantiate_and_fails_with_exception(): void
    {
        config(['self-update.repository_types.github.repository_vendor' => '']);

        $this->expectException(\Exception::class);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
    }

    /** @test */
    public function it_can_run_update(): void
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        /** @var Release $release */
        $release = resolve(Release::class);
        $release->setStoragePath((string) config('self-update.repository_types.github.download_path'))
                ->setVersion('1.0')
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('some-local-file')
                ->download($this->getMockedClient([$this->getResponse200ZipFile()]));
        $release->extract();

        Event::fake();

        $this->assertTrue($github->update($release));

        Event::assertDispatched(UpdateSucceeded::class, 1);
        Event::assertDispatched(UpdateSucceeded::class, function (UpdateSucceeded $e) use ($release) {
            return $e->getVersionUpdatedTo() === $release->getVersion();
        });
    }

    /** @test */
    public function it_can_get_the_version_installed(): void
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->assertEmpty($github->getVersionInstalled());

        config(['self-update.version_installed' => '1.0']);
        $this->assertEquals('1.0', $github->getVersionInstalled());
    }

    /** @test */
    public function it_cannot_get_new_version_available_and_fails_with_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->isNewVersionAvailable('');
    }

    /** @test */
    public function it_can_get_new_version_available_from_type_tag_without_version_file(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('tag'),
            $this->getResponse200Type('tag'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->deleteVersionFile();

        $github->setAccessToken('123');

        Event::fake();

        $this->assertFalse($github->isNewVersionAvailable('2.7'));
        $this->assertTrue($github->isNewVersionAvailable('1.1'));

        Event::assertDispatched(UpdateAvailable::class, 1);
        Event::assertDispatched(UpdateAvailable::class, function (UpdateAvailable $e) use ($github) {
            return $e->getVersionAvailable() === $github->getVersionAvailable();
        });
    }

    /** @test */
    public function it_can_get_new_version_available_from_type_tag_with_version_file(): void
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->setVersionFile('v2.7');

        $this->assertFalse($github->isNewVersionAvailable('v2.7'));

        $github->setVersionFile('v2.7');
        $this->assertTrue($github->isNewVersionAvailable('v1.1'));

        $this->assertEquals('v2.7', $github->getVersionFile());
    }

    /** @test */
    public function it_can_get_new_version_available_from_type_branch_without_version_file(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        $client = $this->getMockedClient([
            $this->getResponse200Type('branch'),
            $this->getResponse200Type('branch'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->deleteVersionFile();

        $this->assertFalse($github->isNewVersionAvailable('2020-02-08T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-04T21:09:15Z'));
    }

    /** @test */
    public function it_can_get_new_version_available_from_type_branch_with_version_file(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->setVersionFile('2020-02-07T21:09:15Z');

        $this->assertFalse($github->isNewVersionAvailable('2020-02-08T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-04T21:09:15Z'));
    }

    /** @test */
    public function it_can_handle_access_tokens_in_github_branch_type_repo(): void
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $github->setAccessTokenPrefix('MyPrefix ');
        $github->setAccessToken('001');

        $this->assertEquals('MyPrefix 001', $github->getAccessToken());
        $this->assertTrue($github->hasAccessToken());
        $this->assertEquals('MyPrefix ', $github->getAccessTokenPrefix());
        $this->assertEquals('001', $github->getAccessToken(false));
    }

    /** @test */
    public function it_can_fetch_github_tag_releases_latest(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('tag'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $release = $github->fetch();

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2.6.1', $release->getVersion());
        $this->assertEquals('2.6.1.zip', $release->getRelease());
    }

    /** @test */
    public function it_can_fetch_github_tag_releases_specific_version(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('tag'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $release = $github->fetch('2.6.0');

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2.6.0', $release->getVersion());
        $this->assertEquals('2.6.0.zip', $release->getRelease());
    }

    /** @test */
    public function it_can_fetch_github_tag_releases_and_takes_latest_if_version_not_available(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('tag'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $release = $github->fetch('v3.22.1');

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2.6.1', $release->getVersion());
        $this->assertEquals('2.6.1.zip', $release->getRelease());
    }

    /** @test */
    public function it_can_fetch_github_branch_releases_latest(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        $client = $this->getMockedClient([
            $this->getResponse200Type('branch'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->setAccessToken('123');

        $release = $github->fetch();

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2020-02-06T21:09:15Z', $release->getVersion());
        $this->assertEquals('e8f19f9b63b5b92f31ddc4a3463dcc231301adea.zip', $release->getRelease());
    }

    /** @test */
    public function it_can_fetch_github_branch_releases_specific_version(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        $client = $this->getMockedClient([
            $this->getResponse200Type('branch'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $release = $github->fetch('2020-02-06T09:35:51Z');

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2020-02-06T09:35:51Z', $release->getVersion());
        $this->assertEquals('4f82f1b9037530baa8775e41e16d82e8db97110f.zip', $release->getRelease());
    }

    /** @test */
    public function it_can_fetch_github_branch_releases_and_takes_latest_if_version_not_available(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        $client = $this->getMockedClient([
            $this->getResponse200Type('branch'),
            $this->getResponse200ZipFile(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $release = $github->fetch('2020-01-01T11:11:11Z');

        $this->assertInstanceOf(Release::class, $release);
        $this->assertEquals('2020-02-06T21:09:15Z', $release->getVersion());
        $this->assertEquals('e8f19f9b63b5b92f31ddc4a3463dcc231301adea.zip', $release->getRelease());
    }

    /** @test */
    public function it_cannot_fetch_github_branch_releases_if_response_empty(): void
    {
        config(['self-update.repository_types.github.use_branch' => 'v2']);

        $client = $this->getMockedClient([
            $this->getResponseEmpty(),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->expectException(Exception::class);
        $github->fetch();
    }
}
