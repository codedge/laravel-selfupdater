<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\Tests\TestCase;
use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;

final class GithubTagTypeTest extends TestCase
{
    public function testIsNewVersionAvailableFailsWithInvalidArgumentException()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->expectException(InvalidArgumentException::class);
        $this->assertInstanceOf(GithubTagType::class, $github);
        $github->isNewVersionAvailable();
    }

    public function testIsNewVersionAvailableTriggerUpdateAvailableEvent()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->expectsEvents(UpdateAvailable::class);
        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertTrue($github->isNewVersionAvailable('v1.1.0'));
    }

    public function testIsNewVersionAvailableVersionFileExists()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $github->deleteVersionFile();

        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertTrue($github->isNewVersionAvailable('v1.1.0'));
        $this->assertFalse($github->isNewVersionAvailable('v100.1'));

    }

    public function testIsNewVersionAvailableVersionFileDoesNotExist()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $github->deleteVersionFile();

        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertTrue($github->isNewVersionAvailable('v1.1.0'));
        $this->assertFalse($github->isNewVersionAvailable('v100.1'));

    }

    public function testGetVersionAvailable()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertNotEmpty($github->getVersionAvailable());
        $this->assertStringStartsWith('v', $github->getVersionAvailable('v'));
        $this->assertStringEndsWith('version', $github->getVersionAvailable('', 'version'));
    }

    public function testFetchingFailsWithException()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->expectException(Exception::class);
        $this->assertInstanceOf(GithubTagType::class, $github);
        $github->fetch();
    }

    public function testHasAccessTokenSet()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $github->setAccessToken('abc123');

        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertTrue($github->hasAccessToken());
        $this->assertEquals($github->getAccessToken(), 'Bearer abc123');
    }

    public function testHasAccessTokenNotSet()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();

        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertFalse($github->hasAccessToken());
        $this->assertEquals($github->getAccessToken(), 'Bearer ');
    }

    public function testSetDifferentAccessTokenPrefix()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->assertInstanceOf(GithubTagType::class, $github);
        $this->assertEquals($github->getAccessTokenPrefix(), 'Bearer ');

        $github->setAccessTokenPrefix('AnotherOne');
        $this->assertEquals($github->getAccessTokenPrefix(), 'AnotherOne');
    }

    public function testGetAccessTokenWithDifferentPrefix()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->assertInstanceOf(GithubTagType::class, $github);
        $github->setAccessTokenPrefix('');

        $this->assertEquals($github->getAccessTokenPrefix(), '');
    }

    public function testGetAccessTokenWithoutPrefix()
    {
        /** @var GithubTagType $github */
        $github = (resolve(GithubRepositoryType::class))->create();
        $this->assertInstanceOf(GithubTagType::class, $github);
        $github->setAccessToken('abc123');

        $this->assertEquals($github->getAccessToken(false),'abc123');
    }
}
