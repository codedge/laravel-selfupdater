<?php

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\AbstractRepositoryType;
use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Events\UpdateFailed;
use File;

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
     * @var array
     */
    protected $config;

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

        return version_compare($version, $this->getVersionAvailable(), '<');
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @return mixed
     */
    public function fetch($version = '')
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));
        $release = $releaseCollection->first();

        $storagePath = $this->config['download_path'];
        $storageFilename = 'latest.zip';

        if (! File::exists($storagePath)) {
            File::makeDirectory($storagePath, 493, true, true);
        }

        if (! empty($version)) {
            $release = $releaseCollection->where('tag_name', $version);
            $storageFilename = "{$version}.zip";
        }

        $storageFile = $storagePath.$storageFilename;
        $this->downloadRelease($this->client, $release->zipball_url, $storageFile);

        $this->unzipArchive($storageFile, $storagePath);
        $this->cleanupGithubSubfoldersInArchive($storagePath);
    }

    /**
     * Perform the actual update process.
     *
     * @return bool
     */
    public function update() : bool
    {
        if ($this->hasCorrectPermissionForUpdate(base_path())) {
            $directoriesCollection = collect(File::directories($this->config['download_path']));
            $directoriesCollection->each(function ($directory) {
                File::moveDirectory($directory, base_path(File::name($directory)), true);
            });

            $filesCollection = collect(File::allFiles($this->config['download_path'], true));
            $filesCollection->each(function ($file) { /* @var \SplFileInfo $file */
                File::move($file->getRealPath(), base_path($file->getFilename()));
            });

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
        return '';
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

        return $prepend.$releaseCollection->first()->tag_name.$append;
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
            self::GITHUB_API_URL.'/repos/'.$this->config['repository_vendor'].'/'.$this->config['repository_name'].'/releases'
        );
    }

    /**
     * Github archives have a sub-folder inside,
     * but we want to have all the content in the main download folder.
     *
     * @param $storagePath
     */
    protected function cleanupGithubSubfoldersInArchive($storagePath)
    {
        $subDirName = File::directories($storagePath);
        $directories = File::directories($subDirName[0]);

        foreach ($directories as $directory) { /* @var string $directory */
            File::moveDirectory($directory, $storagePath.'/'.File::name($directory));
        }

        $files = File::allFiles($subDirName[0], true);
        foreach ($files as $file) { /* @var \SplFileInfo $file */
            File::move($file->getRealPath(), $storagePath.'/'.$file->getFilename());
        }

        File::deleteDirectory($subDirName[0]);
    }
}
