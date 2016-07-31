<?php

namespace Codedge\Updater;

use File;

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
     * @param bool   $deleteZipArchive
     *
     * @return bool
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
}
