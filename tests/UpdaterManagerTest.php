<?php declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\SourceRepository;
use Codedge\Updater\UpdaterManager;
use InvalidArgumentException;

class UpdaterManagerTest extends Testcase
{
    public function test_can_be_instantiated()
    {
        $manager = new UpdaterManager(app());

        $this->assertInstanceOf(UpdaterManager::class, $manager);
    }

    public function test_get_source_repository_with_default_name()
    {
        $manager = new UpdaterManager(app());
        $result = $manager->source();

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    public function test_get_source_repository_with_name_github()
    {
        $manager = new UpdaterManager(app());
        $result = $manager->source('github');

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    public function test_get_source_repository_with_name_http()
    {
        $manager = new UpdaterManager(app());
        $result = $manager->source('http');

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    public function test_get_source_repository_with_not_existing_name()
    {
        $manager = new UpdaterManager(app());

        $this->expectException(InvalidArgumentException::class);
        $manager->source('test');
    }
}
