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
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * HttpRepositoryType.php.
 *
 * @author Steve Hegenbart <steve.hegenbart@kingstarter.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
class HttpRepositoryType implements SourceRepositoryTypeContract
{
    use UseVersionFile, SupportPrivateAccessToken;

    /**
     * @var Client
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
     * @param Client $client
     * @param UpdateExecutor $updateExecutor
     */
    public function __construct(array $config, Client $client, UpdateExecutor $updateExecutor)
    {
        $this->client = $client;
        $this->config = $config;
        $this->config['version_installed'] = config('self-update.version_installed');
        $this->config['exclude_folders'] = config('self-update.exclude_folders');

        // Get prepend and append strings
        $this->prepend = preg_replace('/_VERSION_.*$/', '', $this->config['pkg_filename_format']);
        $this->append = preg_replace('/^.*_VERSION_/', '', $this->config['pkg_filename_format']);

        $this->release = resolve(Release::class);
        $this->release->setStoragePath(Str::finish($this->config['download_path'], DIRECTORY_SEPARATOR))
                      ->setUpdatePath(base_path(), config('self-update.exclude_folders'))
                      ->setAccessToken($config['private_access_token']);
    }

    /**
     * Check repository if a newer version than the installed one is available.
     *
     * @param string $currentVersion
     *
     * @throws \InvalidArgumentException
     * @throws Exception
     *
     * @return bool
     */
    public function isNewVersionAvailable($currentVersion = ''): bool
    {
        $version = $currentVersion ?: $this->getVersionInstalled();

        if (! $version) {
            throw new \InvalidArgumentException('No currently installed version specified.');
        }

        // Remove the version file to forcefully update current version
        $this->deleteVersionFile();

        if (version_compare($version, $this->getVersionAvailable(), '<')) {
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
        if (($releaseCollection = $this->getPackageReleases())->isEmpty()) {
            throw new Exception('Cannot find a release to update. Please check the repository you\'re pulling from');
        }

        $release = $releaseCollection->first();
        $storagePath = Str::finish($this->config['download_path'], '/');

        if (! File::exists($storagePath)) {
            File::makeDirectory($storagePath, 493, true, true);
        }

        if (! $version) {
            $release = $releaseCollection->where('name', $version)->first();
            if (! $release) {
                throw new Exception('Given version was not found in release list.');
            }
        }

        $versionName = $this->prepend.$release->name.$this->append;
        $storageFilename = $versionName.'.zip';

        if (! $this->isSourceAlreadyFetched($release->name)) {
            $storageFile = $storagePath.'/'.$storageFilename;
            $this->downloadRelease($this->client, $release->zipball_url, $storageFile);
            $this->unzipArchive($storageFile, $storagePath.'/'.$versionName);
        }
    }

    /**
     * @param Release $release
     *
     * @return bool
     * @throws \Exception
     */
    public function update(Release $release): bool
    {
        return $this->updateExecutor->run($release);
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
    public function getVersionInstalled($prepend = '', $append = ''): string
    {
        return $this->config['version_installed'];
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
            $releaseCollection = $this->getPackageReleases();
            if ($releaseCollection->isEmpty()) {
                return '';
            }
            $version = $releaseCollection->first()->name;
        }

        return $version;
    }

    /**
     * Retrieve html body with list of all releases from archive URL.
     *
     *@throws Exception
     *
     * @return mixed|ResponseInterface
     */
    protected function getPackageReleases()
    {
        if (empty($url = $this->config['repository_url'])) {
            throw new Exception('No repository specified. Please enter a valid URL in your config.');
        }

        $format = str_replace('_VERSION_', '\d+\.\d+\.\d+',
                    str_replace('.', '\.', $this->config['pkg_filename_format'])
                  ).'.zip';
        $count = preg_match_all(
            "/<a.*href=\".*$format\">($format)<\/a>/i",
            $this->client->get($url)->getBody()->getContents(),
            $files);
        $collection = [];
        $url = preg_replace('/\/$/', '', $url);
        for ($i = 0; $i < $count; $i++) {
            $basename = preg_replace("/^$this->prepend/", '',
                          preg_replace("/$this->append$/", '',
                            preg_replace('/.zip$/', '', $files[1][$i])
                        ));
            array_push($collection, (object) [
                'name' => $basename,
                'zipball_url' => $url.'/'.$files[1][$i],
            ]);
        }
        // Sort collection alphabetically descending to have newest package as first
        array_multisort($collection, SORT_DESC);

        return new Collection($collection);
    }
}
