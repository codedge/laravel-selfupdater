<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes\GithubRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\SourceRepositoryTypes\GithubRepositoryType;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

final class GithubTagType extends GithubRepositoryType implements SourceRepositoryTypeContract
{
    protected ClientInterface $client;

    protected Release $release;

    public function __construct(array $config, ClientInterface $client, UpdateExecutor $updateExecutor)
    {
        parent::__construct($config, $updateExecutor);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($config['private_access_token']);

        $this->client = $client;
    }

    /**
     * Get the latest version that has been published in a certain repository.
     * Example: 2.6.5 or v2.6.5.
     *
     * @param string $prepend Prepend a string to the latest version
     * @param string $append  Append a string to the latest version
     *
     * @throws Exception
     */
    public function getVersionAvailable(string $prepend = '', string $append = ''): string
    {
        if ($this->versionFileExists()) {
            $version = $prepend.$this->getVersionFile().$append;
        } else {
            $response = $this->getRepositoryReleases();

            $releaseCollection = collect(json_decode($response->getBody()->getContents()));
            $version = $prepend.$releaseCollection->first()->tag_name.$append;
        }

        return $version;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     *
     * @throws Exception
     */
    public function fetch(string $version = ''): Release
    {
        $response = $this->getRepositoryReleases();

        try {
            $releases = collect(Utils::jsonDecode($response->getBody()->getContents()));
        } catch (InvalidArgumentException $e) {
            throw ReleaseException::noReleaseFound($version);
        }

        if ($releases->isEmpty()) {
            throw ReleaseException::noReleaseFound($version);
        }

        $release = $this->selectRelease($releases, $version);

        $this->release->setVersion($release->tag_name)
                      ->setRelease($release->tag_name.'.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl($release->zipball_url);

        if (!$this->release->isSourceAlreadyFetched()) {
            $this->release->download($this->client);
            $this->release->extract();
        }

        return $this->release;
    }

    public function selectRelease(Collection $collection, string $version): object
    {
        $release = $collection->first();

        if (!empty($version)) {
            if ($collection->contains('tag_name', $version)) {
                $release = $collection->where('tag_name', $version)->first();
            } else {
                Log::info('No release for version "'.$version.'" found. Selecting latest.');
            }
        }

        return $release;
    }

    protected function getRepositoryReleases(): ResponseInterface
    {
        $url = '/repos/'.$this->config['repository_vendor']
               .'/'.$this->config['repository_name']
               .'/releases';

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $url, ['headers' => $headers]);
    }
}
