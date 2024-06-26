<?php

declare(strict_types=1);

namespace Codedge\Updater\SourceRepositoryTypes;

use Codedge\Updater\Contracts\SourceRepositoryTypeContract;
use Codedge\Updater\Events\UpdateAvailable;
use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Exceptions\VersionException;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Models\UpdateExecutor;
use Codedge\Updater\Traits\UseVersionFile;
use Exception;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GitlabRepositoryType implements SourceRepositoryTypeContract
{
    use UseVersionFile;

    const BASE_URL = 'https://gitlab.com';

    protected array $config;
    protected Release $release;
    protected UpdateExecutor $updateExecutor;

    public function __construct(UpdateExecutor $updateExecutor)
    {
        $this->config = config('self-update.repository_types.gitlab');

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($this->config['private_access_token']);

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
            throw VersionException::versionInstalledNotFound();
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
            $response = $this->getReleases();

            $releaseCollection = collect(json_decode($response->body()));
            $version = $prepend.$releaseCollection->first()->tag_name.$append;
        }

        return $version;
    }

    /**
     * @throws ReleaseException
     */
    public function fetch(string $version = ''): Release
    {
        $response = $this->getReleases();

        try {
            $releases = collect(Utils::jsonDecode($response->body()));
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
                      ->setDownloadUrl($release->assets->sources[0]->url);

        if (!$this->release->isSourceAlreadyFetched()) {
            $this->release->download();
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

    /**
     * @return array{base_url:string, url:string}
     */
    final public function getReleaseUrl(): array
    {
        return [
            'base_url' => $this->config['base_url'] ?? self::BASE_URL,
            'url'      => '/api/v4/projects/'.$this->config['repository_id'].'/releases',
        ];
    }

    final public function getReleases(): Response
    {
        $headers = [];

        if ($this->release->hasAccessToken()) {
            $headers = [
                'PRIVATE-TOKEN' => $this->release->getAccessToken(false),
            ];
        }

        $urls = $this->getReleaseUrl();

        return Http::withHeaders($headers)->baseUrl($urls['base_url'])->get($urls['url']);
    }
}
