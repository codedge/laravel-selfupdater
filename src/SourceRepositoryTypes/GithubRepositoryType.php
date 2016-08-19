<?php

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\AbstractRepositoryType;
use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use File;
use GuzzleHttp\Client;

/**
 * Github.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class GithubRepositoryType extends AbstractRepositoryType implements SourceRepositoryTypeContract
{
    const GITHUB_API_URL = 'https://api.github.com';

    /**
     * @var Client
     */
    protected $client;

    /**
     * Github constructor.
     *
     * @param Client $client
     * @param array  $config
     */
    public function __construct(Client $client, array $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Check repository if a newer version than the installed one is available.
     *
     * @param string $currentVersion
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     *
     * @return bool
     */
    public function isNewVersionAvailable($currentVersion = '') : bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (empty($version) && empty($currentVersion)) {
            throw new \InvalidArgumentException('No currently installed version specified.');
        } elseif (empty($version) && empty($this->getVersionInstalled())) {
            throw new \Exception('Currently installed version cannot be determined.');
        }

        if (version_compare($version, $this->getVersionAvailable(), '<')) {
            event(new UpdateAvailable($this));

            return true;
        }

        return false;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function fetch($version = '')
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));

        if ($releaseCollection->isEmpty()) {
            throw new \Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $releaseCollection->first();

        $storagePath = $this->config['download_path'];

        if (! File::exists($storagePath)) {
            File::makeDirectory($storagePath, 493, true, true);
        }

        if (! empty($version)) {
            $release = $releaseCollection->where('name', $version)->first();
        }

        $storageFilename = "{$release->name}.zip";

        if (! $this->isSourceAlreadyFetched($release->name)) {
            $storageFile = $storagePath.$storageFilename;
            $this->downloadRelease($this->client, $release->zipball_url, $storageFile);

            $this->unzipArchive($storageFile, $storagePath);
            $this->createReleaseFolder($storagePath, $release->name);
        }
    }

    /**
     * Perform the actual update process.
     *
     * @param string $version
     *
     * @return bool
     */
    public function update($version = '') : bool
    {
        if ($this->hasCorrectPermissionForUpdate(base_path())) {
            if (! empty($version)) {
                $sourcePath = $this->config['download_path'].DIRECTORY_SEPARATOR.$version;
            } else {
                $sourcePath = File::directories($this->config['download_path'])[0];
            }

            $directoriesCollection = collect(File::directories($sourcePath));
            $directoriesCollection->each(function ($directory) {
                File::moveDirectory($directory, base_path(File::name($directory)), true);
            });

            $filesCollection = collect(File::allFiles($sourcePath, true));
            $filesCollection->each(function ($file) { /* @var \SplFileInfo $file */
                File::copy($file->getRealPath(), base_path($file->getFilename()));
            });

            File::deleteDirectory($sourcePath);
            event(new UpdateSucceeded($this));

            return true;
        }

        event(new UpdateFailed($this));

        return false;
    }

    /**
     * Get the version that is currenly installed.
     * Example: 1.1.0 or v1.1.0 or "1.1.0 version".
     *
     * @param string $prepend
     * @param string $append
     *
     * @return string
     */
    public function getVersionInstalled($prepend = '', $append = '') : string
    {
        return $prepend.$this->config['version_installed'].$append;
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     *
     * @return string
     */
    public function getVersionAvailable($prepend = '', $append = '') : string
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));

        return $prepend.$releaseCollection->first()->name.$append;
    }

    /**
     * Get all releases for a specific repository.
     *
     * @throws \Exception
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function getRepositoryReleases()
    {
        if (empty($this->config['repository_vendor']) || empty($this->config['repository_name'])) {
            throw new \Exception('No repository specified. Please enter a valid Github repository owner and name in your config.');
        }

        return $this->client->request(
            'GET',
            self::GITHUB_API_URL.'/repos/'.$this->config['repository_vendor'].'/'.$this->config['repository_name'].'/tags'
        );
    }
}
