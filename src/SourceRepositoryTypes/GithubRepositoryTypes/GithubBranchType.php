<?php declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;use Psr\Http\Message\ResponseInterface;

final class GithubBranchType extends GithubRepositoryType implements GithubRepositoryTypeContract
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(array $config, Client $client)
    {
        $this->config = $config;
        $this->setAccessToken($config['private_access_token']);

        $this->client = $client;
        $this->storagePath = Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR);
    }

    public function fetch(string $version = '')
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()->getContents()));

        if ($releaseCollection->isEmpty()) {
            throw new \Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        if (! File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 493, true, true);
        }

        $release = $releaseCollection->first();

        if (! empty($version)) {
            $release = $releaseCollection->where('sha', $version)->first();
        }

        $storageFolder = $this->storagePath . $release->sha . '-' . now()->timestamp;
        $storageFilename = $storageFolder . '.zip';

        if (! $this->isSourceAlreadyFetched($release->sha)) {
            $this->downloadRelease($this->client, $this->generateZipUrl($release->sha), $storageFilename);
            $this->unzipArchive($storageFilename, $storageFolder);
            $this->createReleaseFolder($storageFolder, $release->sha);
        }
    }

    /**
     * Check repository if a newer version than the installed one is available.
     * For updates that are pulled from a commit just checking the SHA won't be enough. So we need to check/compare
     * the commits and dates.
     *
     * @param string $currentVersion
     *
     * @throws InvalidArgumentException
     * @throws Exception
     *
     * @return bool
     */
    public function isNewVersionAvailable($currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (! $version) {
            throw new InvalidArgumentException('No currently installed version specified.');
        }

        $versionAvailable = $this->getVersionAvailable();

        #dd($version, $versionAvailable, version_compare($version, $versionAvailable, '<'));

        if (version_compare($version, $versionAvailable, '<')) {
            if (! $this->versionFileExists()) {
                $this->setVersionFile($versionAvailable);
            }
            event(new UpdateAvailable($versionAvailable));

            return true;
        }

        return false;
    }

    public function getVersionAvailable(string $prepend = '', string $append = '' ): string
    {
        if ($this->versionFileExists()) {
            $version = $prepend.$this->getVersionFile().$append;
        } else {
            $response = $this->getRepositoryReleases();
            $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()));
            $version = $prepend.$releaseCollection->first()->commit->author->date.$append;
        }

        return $version;
    }

    protected function getRepositoryReleases(): ResponseInterface
    {
        $url = self::GITHUB_API_URL
               . DIRECTORY_SEPARATOR . 'repos'
               . DIRECTORY_SEPARATOR . $this->config['repository_vendor']
               . DIRECTORY_SEPARATOR . $this->config['repository_name']
               . DIRECTORY_SEPARATOR . 'commits'
               . '?sha=' . $this->config['use_branch'];

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $url, [ 'headers' => $headers, ]);
    }

    private function generateZipUrl(string $name): string
    {
        return self::GITHUB_URL
               . DIRECTORY_SEPARATOR . $this->config['repository_vendor']
               . DIRECTORY_SEPARATOR . $this->config['repository_name']
               . DIRECTORY_SEPARATOR . 'archive'
               . DIRECTORY_SEPARATOR . $name . '.zip';
    }
}
