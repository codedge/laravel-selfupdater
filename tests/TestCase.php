<?php declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\UpdaterFacade;
use Codedge\Updater\UpdaterServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
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
                ],
                'http' => [
                    'type' => 'http',
                    'repository_url' => env('SELF_UPDATER_REPO_URL', ''),
                    'pkg_filename_format' => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
                    'download_path' => env('SELF_UPDATER_DOWNLOAD_PATH', '/tmp'),
                ],
            ],
            'log_events' => false,
            'mail_to' => [
                'address' => '',
                'name' => '',
            ],
        ]);
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
