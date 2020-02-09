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
    /**
     * @var Client;
     */
    protected $client;

    /**
     * @var array
     */
    protected $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->app['config']['self-update']['repository_types']['github'];
        $this->config['use_branch'] = 'v2';
        $this->client = $this->getMockedClient('branch');
    }

    public function testIsNewVersionAvailableFailsWithInvalidArgumentException()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();
        $this->expectException(InvalidArgumentException::class);
        $github->isNewVersionAvailable();
    }

    public function testIsNewVersionAvailableTriggerUpdateAvailableEvent()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $github->deleteVersionFile();

        $this->expectsEvents(UpdateAvailable::class);
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testIsNewVersionAvailableVersionFileDoesNotExist()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $github->deleteVersionFile();

        $this->assertFalse($github->isNewVersionAvailable('2020-02-07T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testIsNewVersionAvailableVersionFileExists()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $this->assertFalse($github->isNewVersionAvailable('2020-02-07T21:09:15Z'));
        $this->assertTrue($github->isNewVersionAvailable('2020-02-05T21:09:15Z'));
    }

    public function testGetVersionAvailable()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $this->assertNotEmpty($github->getVersionAvailable());
        $this->assertStringStartsWith('v', $github->getVersionAvailable('v'));
        $this->assertStringEndsWith('version', $github->getVersionAvailable('', 'version'));
    }

    public function testFetchingFailsWithException()
    {
        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType(new Client(), $this->config))->create();
        $this->expectException(Exception::class);
        $github->fetch();
    }
}
