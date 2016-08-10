<?php

namespace Codedge\Updater\Tests\SourceRepositoryTypes;

use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Codedge\Updater\Tests\TestCase;

class GithubRepositoryTypeTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->config = [
            'type' => 'github',
            'repository_vendor' => 'invoiceninja',
            'repository_name' => 'invoiceninja',
            'repository_url' => '',
            'download_path' => '/tmp',
        ];
    }

    public function testIsNewVersionAvailableFailsWithInvalidArgumentException()
    {
        $class = new GithubRepositoryType($this->client, $this->config);
        $this->expectException(\InvalidArgumentException::class);
        $class->isNewVersionAvailable();
    }

    public function testIsNewVersionAvailableTriggerUpdateAvailableEvent()
    {
        $class = new GithubRepositoryType($this->client, $this->config);
        $currentVersion = 'v1.1.0';

        $this->expectsEvents(UpdateAvailable::class);
        $this->assertTrue($class->isNewVersionAvailable($currentVersion));
    }

    public function testIsNewVersionAvailable()
    {
        $class = new GithubRepositoryType($this->client, $this->config);

        $currentVersion = 'v1.1.0';
        $this->assertTrue($class->isNewVersionAvailable($currentVersion));

        $currentVersion = 'v100.1';
        $this->assertFalse($class->isNewVersionAvailable($currentVersion));

    }

    public function testGetVersionAvailableFailsWithException()
    {
        $class = new GithubRepositoryType($this->client, []);
        $this->expectException(\Exception::class);
        $class->getVersionAvailable();
    }

    public function testGetVersionAvailable()
    {
        $class = new GithubRepositoryType($this->client, $this->config);
        $this->assertNotEmpty($class->getVersionAvailable());
        $this->assertStringStartsWith('v', $class->getVersionAvailable('v'));
        $this->assertStringEndsWith('version', $class->getVersionAvailable('', 'version'));
    }

    /*public function testUpdateTriggerUpdateFailedEvent()
    {
        $class = new GithubRepositoryType($this->client, $this->config);
        $this->expectsEvents(UpdateFailed::class);
        $class->update();
    }*/
}