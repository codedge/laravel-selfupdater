<?php declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubBranchType;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes\GithubTagType;
use Codedge\Updater\SourceRepositoryTypes\HttpRepositoryType;
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
                    'repository_url' => 'https://github.com/invoiceninja/invoiceninja/releases',
                    'pkg_filename_format' => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
                    'download_path' => env('SELF_UPDATER_DOWNLOAD_PATH', '/tmp'),
                    'private_access_token' => '',
                ],
            ],
            'exclude_folders' => [],
            'log_events' => false,
            'mail_to' => [
                'address' => '',
                'name' => '',
            ],
            'artisan_commands' => [
                'pre_update' => [
                    //'command:signature' => [
                    //    'class' => Command class
                    //    'params' => []
                    //]
                ],
                'post_update' => [

                ],
            ],
        ]);

        $app->bind(GithubBranchType::class, function (): GithubRepositoryTypeContract {
            return new GithubBranchType(
                config('self-update.repository_types.github'),
                $this->getMockedClient('branch'),
                resolve(UpdateExecutor::class)
            );
        });

        $app->bind(GithubTagType::class, function (): GithubRepositoryTypeContract {
            return new GithubTagType(
                config('self-update.repository_types.github'),
                $this->getMockedClient('tag'),
                resolve(UpdateExecutor::class)
            );
        });

        $app->bind(HttpRepositoryType::class, function() {
            return new HttpRepositoryType(
                config('self-update.repository_types.http'),
                $this->getMockedClient('http'),
                resolve(UpdateExecutor::class)
            );
        });

    }

    protected function getMockedClient(string $type): Client
    {
        $responses = [
            $this->getResponse200Type($type),
            $this->getResponse200Type($type),
            $this->getResponse200Type($type),
            $this->getResponse200Type($type)
        ];

        if($type === 'http') {
            $responses = [
                $this->getResponse200Type('http'),
                $this->getResponse200ZipFile(),
                $this->getResponse200Type('http'),
                $this->getResponse200ZipFile(),
            ];
        }

        $handler = HandlerStack::create(new MockHandler($responses));

        return new Client(['handler' => $handler]);
    }

    protected function getMockedDownloadZipFileClient(): Client
    {
        $handler = HandlerStack::create(new MockHandler([ $this->getResponse200ZipFile() ]));

        return new Client(['handler' => $handler]);
    }

    protected function getResponse200Type(string $type): Response
    {
        return new Response(
            200, [ 'Content-Type' => 'application/json' ],
            \GuzzleHttp\Psr7\stream_for(fopen('tests/Data/'.$this->mockedResponses[$type], 'r')));
    }

    protected function getResponse200ZipFile(): Response
    {
        return $response = new Response(
            200,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="zip_file.zip"',
            ],
            fopen(__DIR__ . '/Data/release-1.2.zip', 'r')
        );
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
