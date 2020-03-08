<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\File;

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
}
