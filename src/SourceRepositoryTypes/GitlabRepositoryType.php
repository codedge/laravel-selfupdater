<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Traits\SupportPrivateAccessToken;
use Codedge\Updater\Traits\UseVersionFile;
use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class GitlabRepositoryType implements SourceRepositoryTypeContract
{
    use UseVersionFile;
    use SupportPrivateAccessToken;

    const API_URL = 'https://gitlab.com/api/v4';

    protected ClientInterface $client;
    protected array $config;
    protected Release $release;
    protected UpdateExecutor $updateExecutor;

    public function __construct(array $config, ClientInterface $client, UpdateExecutor $updateExecutor)
    {
        $this->client = $client;
        $this->config = $config;

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($config['private_access_token']);

        $this->updateExecutor = $updateExecutor;
    }

    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
    }

    public function isNewVersionAvailable(string $currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (!$version) {
            throw new InvalidArgumentException('No currently installed version specified.');
        }

        $versionAvailable = $this->getVersionAvailable();

        if (version_compare($version, $versionAvailable, '<')) {
            if (!$this->versionFileExists()) {
                $this->setVersionFile($versionAvailable);
            }
            event(new UpdateAvailable($versionAvailable));

            return true;
        }

        return false;
    }

    public function getVersionInstalled(): string
    {
        return (string) config('self-update.version_installed');
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

    public function fetch(string $version = ''): Release
    {
        $response = $this->getRepositoryReleases();

        $releases = collect(Utils::jsonDecode($response->getBody()->getContents()));

        if ($releases->isEmpty()) {
            throw new \Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $this->selectRelease($releases, $version);

        $this->release->setVersion($release->tag_name)
                      ->setRelease($release->tag_name.'.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl($release->assets->sources[0]->url);

        if (!$this->release->isSourceAlreadyFetched()) {
            $this->release->download($this->client);
            $this->release->extract();
        }

        return $this->release;
    }

    public function selectRelease(Collection $collection, string $version)
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
        $url = '/projects/'.$this->config['repository_id'].'/repository'.'/releases';

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $url, ['headers' => $headers]);
    }
}
