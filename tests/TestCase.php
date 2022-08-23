<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests;

use Codedge\Updater\UpdaterFacade;
use Codedge\Updater\UpdaterServiceProvider;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    const DOWNLOAD_PATH = '/tmp/self-updater';

    /** @var array<string, string> */
    protected array $mockedResponses = [
        'tag'    => 'releases-tag.json',
        'branch' => 'releases-branch.json',
        'http'   => 'releases-http_gh.json',
        'gitlab' => 'releases-gitlab.json',
        'gitea'  => 'releases-gitea.json',
    ];

    /**
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('self-update.repository_types', [
            'github' => [
                'type'                 => 'github',
                'repository_vendor'    => 'laravel',
                'repository_name'      => 'laravel',
                'repository_url'       => '',
                'download_path'        => self::DOWNLOAD_PATH,
                'private_access_token' => '',
                'use_branch'           => '',
            ],
            'gitlab' => [
                'type'                 => 'gitlab',
                'repository_id'        => '35488518',
                'download_path'        => self::DOWNLOAD_PATH,
                'private_access_token' => '',
            ],
            'http' => [
                'type'                 => 'http',
                'repository_url'       => 'https://github.com/invoiceninja/invoiceninja/releases',
                'pkg_filename_format'  => env('SELF_UPDATER_PKG_FILENAME_FORMAT', 'v_VERSION_'),
                'download_path'        => self::DOWNLOAD_PATH,
                'private_access_token' => '',
            ],
            'gitea' => [
                'type'                 => 'gitea',
                'repository_vendor'    => 'phillopp',
                'gitea_url'            => 'https://try.gitea.io',
                'repository_name'      => 'emptyRepo',
                'download_path'        => self::DOWNLOAD_PATH,
                'private_access_token' => '',
            ],
        ]);
    }

    protected function getResponse200HttpType(): PromiseInterface
    {
        $stream = Utils::streamFor(fopen('tests/Data/Http/'.$this->mockedResponses['http'], 'r'));
        $response = $stream->getContents();

        return Http::response($response, 200, [
            'Content-Type' => 'application/html',
        ]);
    }

    protected function getResponse200Type(string $type): PromiseInterface
    {
        $stream = Utils::streamFor(fopen('tests/Data/'.$this->mockedResponses[$type], 'r'));
        $response = json_decode($stream->getContents(), true);

        return Http::response($response, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    protected function getResponse200ZipFile(): PromiseInterface
    {
        return Http::response(
            fopen(__DIR__.'/Data/release-1.2.zip', 'r'),
            200,
            [
                'Content-Type'        => 'application/zip',
                'Content-Disposition' => 'attachment; filename="release-1.2.zip"',
            ],
        );
    }

    protected function getResponseEmpty(): PromiseInterface
    {
        return Http::response(
            '',
            200,
            ['Content-Type' => 'text/html'],
        );
    }

    protected function resetDownloadDir(): void
    {
        /** @var Filesystem $filesystem */
        $filesystem = $this->app->make(Filesystem::class);

        if ($filesystem->exists(self::DOWNLOAD_PATH)) {
            $filesystem->deleteDirectory(self::DOWNLOAD_PATH);
        }

        $filesystem->makeDirectory(self::DOWNLOAD_PATH);
    }

    /**
     * @param Application $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            UpdaterServiceProvider::class,
        ];
    }

    /**
     * @param Application $app
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Updater' => UpdaterFacade::class,
        ];
    }
}
