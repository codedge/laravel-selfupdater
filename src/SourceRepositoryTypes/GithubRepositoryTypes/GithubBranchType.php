<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

final class GithubBranchType extends GithubRepositoryType implements GithubRepositoryTypeContract
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

        $release = $this->selectRelease($releaseCollection, $version);

        $storageFolder = $this->storagePath.$release->commit->author->date.'-'.now()->timestamp;
        $storageFilename = $storageFolder.'.zip';

        if (! $this->isSourceAlreadyFetched($release->commit->author->date)) {
            $this->downloadRelease($this->client, $this->generateZipUrl($release->sha), $storageFilename);
            $this->unzipArchive($storageFilename, $storageFolder);
            $this->createReleaseFolder($storageFolder, $release->commit->author->date);
        }
    }

    public function selectRelease(Collection $collection, string $version)
    {
        $release = $collection->first();

        if (! empty($version)) {
            if ($collection->contains('commit.author.date', $version)) {
                $release = $collection->where('commit.author.date', $version)->first();
            } else {
                Log::info('No release for version "'.$version.'" found. Selecting latest.');
            }
        }

        return $release;
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

        if (version_compare($version, $versionAvailable, '<')) {
            if (! $this->versionFileExists()) {
                $this->setVersionFile($versionAvailable);
            }
            event(new UpdateAvailable($versionAvailable));

            return true;
        }

        return false;
    }

    public function getVersionAvailable(string $prepend = '', string $append = ''): string
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
        $url = DIRECTORY_SEPARATOR.'repos'
               .DIRECTORY_SEPARATOR.$this->config['repository_vendor']
               .DIRECTORY_SEPARATOR.$this->config['repository_name']
               .DIRECTORY_SEPARATOR.'commits'
               .'?sha='.$this->config['use_branch'];

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $url, ['headers' => $headers]);
    }

    private function generateZipUrl(string $name): string
    {
        return self::GITHUB_URL
               .DIRECTORY_SEPARATOR.$this->config['repository_vendor']
               .DIRECTORY_SEPARATOR.$this->config['repository_name']
               .DIRECTORY_SEPARATOR.'archive'
               .DIRECTORY_SEPARATOR.$name.'.zip';
    }
}
