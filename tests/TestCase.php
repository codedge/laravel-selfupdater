<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\UpdaterFacade;
use Codedge\Updater\UpdaterServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    const DOWNLOAD_PATH = '/tmp/self-updater';

    /**
     * @var array
     */
    protected $mockedResponses = [
        'tag' => 'releases-tag.json',
        'branch' => 'releases-branch.json',
        'http' => 'releases-http.json',
    ];

    protected $client;

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('self-update.repository_types', [
            'github' => [
                'type' => 'github',
                'repository_vendor' => 'laravel',
                'repository_name' => 'laravel',
                'repository_url' => '',
                'download_path' => self::DOWNLOAD_PATH,
                'private_access_token' => '',
                'use_branch' => '',
            ],
            'http' => [
                'type' => 'http',
                'repository_url' => 'https://github.com/invoiceninja/invoiceninja/releases',
                'pkg_filename_format' => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
                'download_path' => self::DOWNLOAD_PATH,
                'private_access_token' => '',
            ],
        ]);
    }

    protected function getMockedClient($responses): Client
    {
        $handler = HandlerStack::create(new MockHandler($responses));

        return new Client(['handler' => $handler]);
    }

    protected function getResponse200Type(string $type): Response
    {
        return new Response(
            200, ['Content-Type' => 'application/json'],
            \GuzzleHttp\Psr7\stream_for(fopen('tests/Data/'.$this->mockedResponses[$type], 'r')));
    }

    protected function getResponse200ZipFile(): Response
    {
        return new Response(
            200,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="release-1.2.zip"',
            ],
            fopen(__DIR__.'/Data/release-1.2.zip', 'r')
        );
    }

    protected function getResponseEmpty(): Response
    {
        return new Response(
            200, ['Content-Type' => 'text/html'], ''
        );
    }

    protected function resetDownloadDir()
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);

        if ($filesystem->exists(self::DOWNLOAD_PATH)) {
            $filesystem->deleteDirectory(self::DOWNLOAD_PATH);
            $filesystem->makeDirectory(self::DOWNLOAD_PATH);
        } else {
            $filesystem->makeDirectory(self::DOWNLOAD_PATH);
        }
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            UpdaterServiceProvider::class,
        ];
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Updater' => UpdaterFacade::class,
        ];
    }
}
