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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

class HttpRepositoryType implements SourceRepositoryTypeContract
{
    use UseVersionFile, SupportPrivateAccessToken;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var Release
     */
    protected $release;

    /**
     * @var string prepend string
     */
    protected $prepend;

    /**
     * @var string append string
     */
    protected $append;

    /**
     * @var UpdateExecutor
     */
    protected $updateExecutor;

    /**
     * Github constructor.
     *
     * @param array $config
     * @param ClientInterface $client
     * @param UpdateExecutor $updateExecutor
     */
    public function __construct(array $config, ClientInterface $client, UpdateExecutor $updateExecutor)
    {
        $this->client = $client;
        $this->config = $config;

        // Get prepend and append strings
        $this->prepend = preg_replace('/_VERSION_.*$/', '', $this->config['pkg_filename_format']);
        $this->append = preg_replace('/^.*_VERSION_/', '', $this->config['pkg_filename_format']);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($config['private_access_token']);

        $this->updateExecutor = $updateExecutor;
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
    public function isNewVersionAvailable($currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (! $version) {
            throw new InvalidArgumentException('No currently installed version specified.');
        }

        $versionAvailable = $this->getVersionAvailable();

        if (version_compare($version, $versionAvailable, '<')) {
            if (! $this->versionFileExists()) {
                $this->setVersionFile($this->getVersionAvailable());
                event(new UpdateAvailable($this->getVersionAvailable()));
            }

            return true;
        }

        return false;
    }

    /**
     * Fetches the latest version. If you do not want the latest version, specify one and pass it.
     *
     * @param string $version
     *
     * @throws Exception
     *
     * @return Release
     */
    public function fetch($version = ''): Release
    {
        $response = $this->getRepositoryReleases();
        $releaseCollection = $this->extractFromHtml($response->getBody()->getContents());

        if ($releaseCollection->isEmpty()) {
            throw new Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $this->selectRelease($releaseCollection, $version);

        $this->release->setVersion($this->prepend.$release->name.$this->append)
                      ->setRelease($this->prepend.$release->name.$this->append.'.zip')
                      ->updateStoragePath()
                      ->setDownloadUrl($release->zipball_url);

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
            if ($collection->contains('name', $version)) {
                $release = $collection->where('name', $version)->first();
            } else {
                Log::info('No release for version "'.$version.'" found. Selecting latest.');
            }
        }

        return $release;
    }

    /**
     * @param Release $release
     *
     * @return bool
     * @throws Exception
     */
    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
    }

    /**
     * {@inheritdoc}
     */
    public function getVersionInstalled(): string
    {
        return (string) config('self-update.version_installed');
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
    public function getVersionAvailable($prepend = '', $append = ''): string
    {
        if ($this->versionFileExists()) {
            $version = $this->getVersionFile();
        } else {
            $releaseCollection = $this->extractFromHtml($this->getRepositoryReleases()->getBody()->getContents());
            $version = $releaseCollection->first()->name;
        }

        return $version;
    }

    /**
     * Retrieve html body with list of all releases from archive URL.
     *
     * @return ResponseInterface
     * @throws Exception
     */
    protected function getRepositoryReleases(): ResponseInterface
    {
        if (empty($this->config['repository_url'])) {
            throw new Exception('No repository specified. Please enter a valid URL in your config.');
        }

        $headers = [];

        if ($this->release->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $this->client->request('GET', $this->config['repository_url'], ['headers' => $headers]);
    }

    private function extractFromHtml(string $content): Collection
    {
        $format = str_replace(
                        '_VERSION_', '(\d+\.\d+\.\d+)',
                        str_replace('.', '\.', $this->config['pkg_filename_format'])
        ).'.zip';
        $linkPattern = '<a.*href="(.*'.$format.')"';

        preg_match_all('/'.$linkPattern.'/i', $content, $files);
        $releaseVersions = $files[2];

        // Extract domain only
        preg_match('/(?:\w+:)?\/\/[^\/]+([^?#]+)/', $this->config['repository_url'], $matches);
        $baseUrl = preg_replace('#'.$matches[1].'#', '', $this->config['repository_url']);

        $releases = collect($files[1])->map(function ($item, $key) use ($baseUrl, $releaseVersions) {
            return (object) [
                'name' => $releaseVersions[$key],
                'zipball_url' => $baseUrl.$item,
            ];
        });

        return new Collection($releases);
    }
}
