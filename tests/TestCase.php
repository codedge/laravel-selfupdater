<?php

namespace Codedge\Updater\Tests;

use GuzzleHttp\Client;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * @var array
     */
    protected $config;


    protected $client;

    public function setUp()
    {
        parent::setUp();

        $this->config = [
            'default' => 'github',
            'repository_types' => [
                'github' => [
                    'type' => 'github',
                    'repository_vendor' => 'laravel',
                    'repository_name' => 'laravel',
                    'repository_url' => '',
                    'download_path' => '/tmp',
                ],
            ],
            'log_events' => false,
            'mail_to' => [
                'address' => '',
                'name' => '',
            ],
        ];

        $this->client = new Client();
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            'Codedge\Updater\UpdaterServiceProvider',
        ];
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Updater' => 'Codedge\Updater\UpdaterFacade',
        ];
    }
}