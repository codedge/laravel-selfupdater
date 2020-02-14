<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\Tests\TestCase;
use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;

final class GithubBranchTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['self-update.repository_types.github.use_branch' => 'v2']);
    }

    public function testIsNewVersionAvailableFailsWithInvalidArgumentException()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->assertInstanceOf(GithubBranchType::class, $github);
        $this->expectException(InvalidArgumentException::class);
        $github->isNewVersionAvailable();
    }

    public function testIsNewVersionAvailableTriggerUpdateAvailableEvent()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $github->deleteVersionFile();

        $this->expectsEvents(UpdateAvailable::class);
        $this->assertInstanceOf(GithubBranchType::class, $github);
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testIsNewVersionAvailableVersionFileDoesNotExist()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $github->deleteVersionFile();

        $this->assertInstanceOf(GithubBranchType::class, $github);
        $this->assertFalse($github->isNewVersionAvailable('2020-02-07T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testIsNewVersionAvailableVersionFileExists()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubBranchType::class, $github);
        $this->assertFalse($github->isNewVersionAvailable('2020-02-07T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testGetVersionAvailable()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubBranchType::class, $github);
        $this->assertNotEmpty($github->getVersionAvailable());
        $this->assertStringStartsWith('v', $github->getVersionAvailable('v'));
        $this->assertStringEndsWith('version', $github->getVersionAvailable('', 'version'));
    }

    public function testFetchingFailsWithException()
    {
        /** @var GithubBranchType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->expectException(Exception::class);
        $github->fetch();
    }
}
