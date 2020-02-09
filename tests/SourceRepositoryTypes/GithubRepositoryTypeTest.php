<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Exceptions\InvalidRepositoryException;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use GuzzleHttp\Client;
use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use InvalidArgumentException;
use Exception;

class GithubRepositoryTypeTest extends TestCase
{
    /**
     * @var Client;
     */
    protected $client;

    /**
     * @var array
     */
    protected $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = $this->app['config']['self-update']['repository_types']['github'];
        $this->client = $this->getMockedClient('tag');
    }

    public function testCreateGithubTagTypeInstance()
    {
        /** @var GithubTagType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $this->assertInstanceOf(GithubTagType::class, $github);

    }

    public function testCreateGithubBranchTypeInstance()
    {
        $this->config['use_branch'] = 'v2';

        /** @var GithubBranchType $github */
        $github = (new GithubRepositoryType($this->client, $this->config))->create();

        $this->assertInstanceOf(GithubBranchType::class, $github);
    }

//    public function testInvalidRepository()
//    {
//        $this->config['repository_vendor'] = '';
//        $this->config['repository_name'] = '';
//
//        $this->withoutExceptionHandling();
//        $this->expectException(InvalidRepositoryException::class);
//
//        /** @var GithubBranchType $github */
//        $github = (new GithubRepositoryType($this->client, $this->config))->create();
//    }
}
