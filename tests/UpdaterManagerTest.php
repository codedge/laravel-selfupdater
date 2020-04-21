<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\SourceRepository;
use Codedge\Updater\UpdaterManager;
use GuzzleHttp\Client;
use InvalidArgumentException;

class UpdaterManagerTest extends Testcase
{
    /** @test */
    public function it_can_be_instantiated()
    {
        $manager = resolve(UpdaterManager::class);

        $this->assertInstanceOf(UpdaterManager::class, $manager);
    }

    /** @test */
    public function it_can_get_source_repository_with_default_name()
    {
        $manager = resolve(UpdaterManager::class);
        $result = $manager->source();

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    /** @test */
    public function it_can_get_source_repository_with_name_github()
    {
        $manager = resolve(UpdaterManager::class);
        $result = $manager->source('github');

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    /** @test */
    public function it_can_get_source_repository_with_name_http()
    {
        $manager = resolve(UpdaterManager::class);
        $result = $manager->source('http');

        $this->assertInstanceOf(SourceRepository::class, $result);
    }

    /** @test */
    public function it_can_get_source_repository_with_not_existing_name()
    {
        $manager = resolve(UpdaterManager::class);

        $this->expectException(InvalidArgumentException::class);
        $manager->source('test');
    }

    /** @test */
    public function it_can_get_new_version_through_updater_manager_available_from_type_tag_without_version_file(): void
    {
        $client = $this->getMockedClient([
            $this->getResponse200Type('tag'),
            $this->getResponse200Type('tag'),
        ]);
        $this->app->instance(Client::class, $client);

        /** @var UpdaterManager $manager */
        $manager = resolve(UpdaterManager::class);

        /** @var SourceRepository $repository */
        $repository = $manager->source('');
        $repository->deleteVersionFile();

        $this->assertFalse($repository->isNewVersionAvailable('2.7'));
        $this->assertTrue($repository->isNewVersionAvailable('1.1'));
    }

    /** @test */
    public function it_can_get_new_version_through_updater_manager_available_from_type_tag_with_version_file(): void
    {
        /** @var UpdaterManager $manager */
        $manager = resolve(UpdaterManager::class);

        /** @var SourceRepository $repository */
        $repository = $manager->source('');
        $repository->setVersionFile('v3.5');

        $this->assertTrue($repository->isNewVersionAvailable('v2.7'));
        $this->assertFalse($repository->isNewVersionAvailable('v4.1'));
    }

    /** @test */
    public function it_can_get_the_version_installed_through_updater_manager_from_tag_type(): void
    {
        /** @var UpdaterManager $manager */
        $manager = resolve(UpdaterManager::class);

        /** @var SourceRepository $repository */
        $repository = $manager->source('');

        $this->assertEmpty($repository->getVersionInstalled());

        config(['self-update.version_installed' => '1.0']);
        $this->assertEquals('1.0', $repository->getVersionInstalled());
    }

    /** @test */
    public function it_can_collect_pre_update_commands(): void
    {
        /** @var UpdaterManager $manager */
        $manager = resolve(UpdaterManager::class);

        /** @var SourceRepository $repository */
        $repository = $manager->source('');

        $this->assertEquals(0, $repository->preUpdateArtisanCommands());
    }

//    public function it_can_run_pre_update_commands(): void
//    {
//        config(['self-update.artisan_commands.pre_update.updater:prepare' => [
//            'class' => \App\Console\Commands\PreUpdateTasks::class,
//            'params' => []
//            ],
//        ]);
//
//        /** @var UpdaterManager $manager */
//        $manager = resolve(UpdaterManager::class);
//
//        /** @var SourceRepository $repository */
//        $repository = $manager->source('');
//
//        $this->assertEquals(1, $repository->preUpdateArtisanCommands());
//    }

    /** @test */
    public function it_can_collect_post_update_commands(): void
    {
        /** @var UpdaterManager $manager */
        $manager = resolve(UpdaterManager::class);

        /** @var SourceRepository $repository */
        $repository = $manager->source('');

        $this->assertEquals(0, $repository->postUpdateArtisanCommands());
    }
}
