<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

final class GithubBranchType extends GithubRepositoryType implements SourceRepositoryTypeContract
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var Release
     */
    protected $release;

    public function __construct(array $config, ClientInterface $client, UpdateExecutor $updateExecutor)
    {
        parent::__construct($config, $updateExecutor);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($config['private_access_token']);

        $this->client = $client;
    }

    public function fetch(string $version = ''): Release
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = collect(Utils::jsonDecode($response->getBody()->getContents()));

        if ($releaseCollection->isEmpty()) {
            throw new \Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $this->selectRelease($releaseCollection, $version);

        $this->release->setVersion($release->commit->author->date)
                      ->setRelease($release->sha.'.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl($this->generateArchiveUrl($release->sha));

        if (! $this->release->isSourceAlreadyFetched()) {
            $this->release->download($this->client);
            $this->release->extract();
        }

        return $this->release;
    }

    /**
     * @param Collection $collection
     * @param string $version
     *
     * @return mixed
     */
    public function selectRelease(Collection $collection, string $version)
    {
        $release = $collection->first();

        if (! empty($version)) {
            if ($collection->contains('commit.author.date', $version)) {
                $release = $collection->where('commit.author.date', $version)->first();
            } else {
                Log::info('No commit for date "'.$version.'" found. Selecting latest.');
            }
        }

        return $release;
    }

    public function getVersionAvailable(string $prepend = '', string $append = ''): string
    {
        if ($this->versionFileExists()) {
            $version = $prepend.$this->getVersionFile().$append;
        } else {
            $response = $this->getRepositoryReleases();
            $releaseCollection = collect(Utils::jsonDecode($response->getBody()->getContents()));
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
        return DIRECTORY_SEPARATOR.'repos'
               .DIRECTORY_SEPARATOR.$this->config['repository_vendor']
               .DIRECTORY_SEPARATOR.$this->config['repository_name']
               .DIRECTORY_SEPARATOR.'zipball'
               .DIRECTORY_SEPARATOR.$name;
    }
}
