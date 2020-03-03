<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\GithubRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Models\Release;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Collection;
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

    /**
     * @var Release
     */
    protected $release;

    public function __construct(array $config, ClientInterface $client)
    {
        parent::__construct($config);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setAccessToken($config['private_access_token']);

        $this->client = $client;
    }

    public function fetch(string $version = '')
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(\GuzzleHttp\json_decode($response->getBody()->getContents()));

        if ($releaseCollection->isEmpty()) {
            throw new \Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $json = $this->selectRelease($releaseCollection, $version);

        $storageFilename = $this->storagePath.$json->commit->author->date.'-'.now()->timestamp.'.zip';

        $this->release->setRelease(pathinfo($storageFilename, PATHINFO_FILENAME))
                      ->setDownloadUrl($this->generateArchiveUrl($json->sha));

        if (! $this->release->isSourceAlreadyFetched()) {
            $this->release->download($this->client);
            $this->release->extract($storageFilename);
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

    private function generateArchiveUrl(string $name): string
    {
        return DIRECTORY_SEPARATOR.$this->config['repository_vendor']
               .DIRECTORY_SEPARATOR.$this->config['repository_name']
               .DIRECTORY_SEPARATOR.'archive'
               .DIRECTORY_SEPARATOR.$name.'.zip';
    }
}
