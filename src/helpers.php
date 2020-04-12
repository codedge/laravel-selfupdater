<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

if (! \function_exists('dirsIntersect')) {
    /**
     * Check if files in one array (i. e. directory) are also exist in a second one.
     *
     * @param array $directory
     * @param array $excludedDirs
     *
     * @return bool
     */
    function dirsIntersect(array $directory, array $excludedDirs): bool
    {
        return count(array_intersect($directory, $excludedDirs)) ? true : false;
    }
}

if (! \function_exists('checkPermissions')) {
    /**
     * Check a given directory recursively if all files are writeable.
     *
     * @param Finder $directory
     *
     * @return bool
     */
    function checkPermissions(Finder $directory): bool
    {
        $checkPermission = true;

        collect($directory->getIterator())->each(function (SplFileInfo $file) use (&$checkPermission) {
            if ($file->isWritable() === false) {
                $checkPermission = false;
            }
        });

        return $checkPermission;
    }
}

if (! \function_exists('createFolderFromFile')) {
    /**
     * Create a folder name including path from a given file.
     * Input: /tmp/my_zip_file.zip
     * Output: /tmp/my_zip_file/.
     *
     * @param string $file
     *
     * @return string
     */
    function createFolderFromFile(string $file): string
    {
        $pathinfo = pathinfo($file);

        return Str::finish($pathinfo['dirname'], DIRECTORY_SEPARATOR).$pathinfo['filename'];
    }
}
