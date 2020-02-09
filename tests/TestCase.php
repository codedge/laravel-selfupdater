<?php declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\UpdaterFacade;
use Codedge\Updater\UpdaterServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @var array
     */
    protected $mockedResponses = [
        'tag' => 'releases-tag.json',
        'branch' => 'releases-branch.json',
    ];

    protected $client;

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('self-update', [
            'default' => 'github',
            'version_installed' => '',
            'repository_types' => [
                'github' => [
                    'type' => 'github',
                    'repository_vendor' => 'laravel',
                    'repository_name' => 'laravel',
                    'repository_url' => '',
                    'download_path' => '/tmp',
                    'private_access_token' => '',
                    'use_branch' => '',
                ],
                'http' => [
                    'type' => 'http',
                    'repository_url' => env('SELF_UPDATER_REPO_URL', ''),
                    'pkg_filename_format' => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
                    'download_path' => env('SELF_UPDATER_DOWNLOAD_PATH', '/tmp'),
                    'private_access_token' => '',
                ],
            ],
            'log_events' => false,
            'mail_to' => [
                'address' => '',
                'name' => '',
            ],
        ]);
    }

    protected function getMockedClient(string $type): Client
    {
        $response = new Response(
            200, [ 'Content-Type' => 'application/json' ],
            \GuzzleHttp\Psr7\stream_for(fopen('tests/Data/'.$this->mockedResponses[$type], 'r')));

        $handler = HandlerStack::create(new MockHandler([
            $response, $response, $response, $response
        ]));

        return new Client(['handler' => $handler]);
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            UpdaterServiceProvider::class,
        ];
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Updater' => UpdaterFacade::class,
        ];
    }
}
