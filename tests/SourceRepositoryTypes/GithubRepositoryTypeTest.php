<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\SourceRepositoryTypes\HttpRepositoryType;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class GithubRepositoryTypeTest extends TestCase
{
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

        $release = resolve(Release::class);
        $release->setStoragePath('/tmp')
                ->setRelease('release-1.0.zip')
                ->updateStoragePath()
                ->setDownloadUrl('some-local-file')
                ->download($this->getMockedDownloadZipFileClient());
        $release->extract();

        $this->assertTrue($github->update($release));
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
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->deleteVersionFile();

        $github->setAccessToken('123');

        $this->assertFalse($github->isNewVersionAvailable('v2.7'));
        $this->assertTrue($github->isNewVersionAvailable('v1.1'));
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

        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->deleteVersionFile();

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
}
