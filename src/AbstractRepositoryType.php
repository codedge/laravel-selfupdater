<?php

namespace Codedge\Updater;

use Codedge\Updater\Events\HasWrongPermissions;
use File;
use GuzzleHttp\Client;

/**
 * AbstractRepositoryType.php.
 *
 * @author Holger Lösken <holger.loesken@codedge.de>
 * @copyright See LICENSE file that was distributed with this source code.
 */
abstract class AbstractRepositoryType
{
    /**
     * Unzip an archive.
     *
     * @param string $file
     * @param string $targetDir
     * @param bool $deleteZipArchive
     *
     * @return bool
     * @throws \Exception
     */
    protected function unzipArchive($file = '', $targetDir = '', $deleteZipArchive = true) : bool
    {
        if (empty($file) || ! File::exists($file)) {
            throw new \InvalidArgumentException("Archive [{$file}] cannot be found or is empty.");
        }

        $zip = new \ZipArchive();
        $res = $zip->open($file);

        if (! $res) {
            throw new \Exception("Cannot open zip archive [{$file}].");
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
     * @param $directory
     *
     * @return bool
     */
    protected function hasCorrectPermissionForUpdate($directory) : bool
    {
        $files = File::allFiles($directory);

        $collection = collect($files)->each(function ($file) { /* @var \SplFileInfo $file */
            if (! File::isWritable($file->getRealPath())) {
                event(new HasWrongPermissions($this));

                return false;
            }
        });

        return true;
    }

    /**
     * Download a file to a given location
     *
     * @param Client $client
     * @param string $source
     * @param string $storagePath
     * 
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    protected function downloadRelease(Client $client, $source, $storagePath)
    {
        return $client->request(
            'GET', $source, ['sink' => $storagePath]
        );
    }
}
