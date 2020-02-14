<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Exception;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

final class GithubTagType extends GithubRepositoryType implements GithubRepositoryTypeContract
{
    /**
     * @var ClientInterface
     */
    protected $client;

    public function __construct(array $config, ClientInterface $client)
    {
        parent::__construct($config);
        $this->setAccessToken($config['private_access_token']);

        $this->client = $client;
        $this->storagePath = Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR);
    }

    /**
     * Check repository if a newer version than the installed one is available.
     *
     * @param string $currentVersion
     *
     * @throws InvalidArgumentException
     * @throws Exception
     *
     * @return bool
     */
    public function isNewVersionAvailable(string $currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (! $version) {
            throw new InvalidArgumentException('No currently installed version specified.');
        }

        $versionAvailable = $this->getVersionAvailable();

        if (version_compare($version, $versionAvailable, '<')) {
            if (! $this->versionFileExists()) {
                $this->setVersionFile($versionAvailable);
            }
            event(new UpdateAvailable($versionAvailable));

            return true;
        }

        return false;
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append Append a string to the latest version
     *
     * @return string
     * @throws Exception
     */
    public function getVersionAvailable(string $prepend = '', string $append = ''): string
    {
        if ($this->versionFileExists()) {
            $version = $prepend.$this->getVersionFile().$append;
        } else {
            $response = $this->getRepositoryReleases();

            $releaseCollection = collect(json_decode($response->getBody()->getContents()));
            $version = $prepend.$releaseCollection->first()->name.$append;
        }

        return $version;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @return void
     * @throws Exception
     */
    public function fetch($version = ''): void
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()->getContents()));

        if ($releaseCollection->isEmpty()) {
            throw new Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $releaseCollection->first();

        if (! File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 493, true, true);
        }

        if (! empty($version)) {
            $release = $releaseCollection->where('name', $version)->first();
        }

        $storageFolder = $this->storagePath.$release->name.'-'.now()->timestamp;
        $storageFilename = $storageFolder.'.zip';

        if (! $this->isSourceAlreadyFetched($release->name)) {
            $this->downloadRelease($this->client, $release->zipball_url, $storageFilename);
            $this->unzipArchive($storageFilename, $storageFolder);
            $this->createReleaseFolder($storageFolder, $release->name);
        }
    }

    protected function getRepositoryReleases(): ResponseInterface
    {
        $url = '/repos/'.$this->config['repository_vendor']
               .'/'.$this->config['repository_name']
               .'/tags';

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $url, ['headers' => $headers]);
    }
}
