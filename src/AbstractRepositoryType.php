<?php

declare(strict_types=1);

namespace Codedge\Updater;

use Codedge\Updater\Events\HasWrongPermissions;
use Exception;
use GuzzleHttp\ClientInterface;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * AbstractRepositoryType.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
abstract class AbstractRepositoryType
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var Finder|SplFileInfo[]
     */
    protected $pathToUpdate;

    /**
     * @var string
     */
    public $storagePath;

    /**
     * Unzip an archive.
     *
     * @param string $file
     * @param string $targetDir
     * @param bool   $deleteZipArchive
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function unzipArchive($file, $targetDir, $deleteZipArchive = true): bool
    {
        if (empty($file) || ! File::exists($file)) {
            throw new \InvalidArgumentException("Archive [{$file}] cannot be found or is empty.");
        }

        $zip = new \ZipArchive();
        $res = $zip->open($file);

        if (! $res) {
            throw new Exception("Cannot open zip archive [{$file}].");
        }

        if (empty($targetDir)) {
            $extracted = $zip->extractTo(File::dirname($file));
        } else {
            $extracted = $zip->extractTo($targetDir);
        }

        $zip->close();

        if ($extracted && $deleteZipArchive === true) {
            File::delete($file);
        }

        return true;
    }

    /**
     * Check a given directory recursively if all files are writeable.
     *
     * @throws Exception
     *
     * @return bool
     */
    protected function hasCorrectPermissionForUpdate(): bool
    {
        if (! $this->pathToUpdate) {
            throw new Exception('No directory set for update. Please set the update with: setPathToUpdate(path).');
        }

        collect($this->pathToUpdate->files())->each(function ($file) { /* @var SplFileInfo $file */
            if (! File::isWritable($file->getRealPath())) {
                event(new HasWrongPermissions($this));

                return false;
            }
        });

        return true;
    }

    /**
     * Download a file to a given location.
     *
     * @param ClientInterface $client
     * @param string $source Url for the source (.zip)
     * @param string $storagePath
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function downloadRelease(ClientInterface $client, string $source, $storagePath)
    {
        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return $client->request(
            'GET',
            $source,
            [
                'sink' => $storagePath,
                'headers' => $headers,
            ]
        );
    }

    /**
     * Check if the source has already been downloaded.
     *
     * @param string $version A specific version
     *
     * @return bool
     */
    protected function isSourceAlreadyFetched($version): bool
    {
        $storagePath = $this->config['download_path'].'/'.$version;
        if (! File::exists($storagePath) || empty(File::directories($storagePath))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Set the paths to be updated.
     *
     * @param string $path    Path where the update should be run into
     * @param array  $exclude List of folder names that shall not be updated
     */
    protected function setPathToUpdate(string $path, array $exclude)
    {
        $finder = (new Finder())->in($path)->exclude($exclude);

        $this->pathToUpdate = $finder;
    }

    /**
     * Create a release sub-folder inside the storage dir.
     *
     * @param string $releaseFolder
     * @param string $releaseName
     */
    public function createReleaseFolder(string $releaseFolder, $releaseName)
    {
        $folders = File::directories($releaseFolder);

        if (count($folders) === 1) {
            // Only one sub-folder inside extracted directory
            File::moveDirectory($folders[0], $this->storagePath.$releaseName);
            File::deleteDirectory($folders[0]);
            File::deleteDirectory($releaseFolder);
        } else {
            // Release (with all files and folders) is already inside, so we need to only rename the folder
            File::moveDirectory($releaseFolder, $this->storagePath.$releaseName);
        }
    }

    /**
     * Check if files in one array (i. e. directory) are also exist in a second one.
     *
     * @param array $directory
     * @param array $excludedDirs
     *
     * @return bool
     */
    public function isDirectoryExcluded(array $directory, array $excludedDirs): bool
    {
        return count(array_intersect($directory, $excludedDirs)) ? true : false;
    }
}
