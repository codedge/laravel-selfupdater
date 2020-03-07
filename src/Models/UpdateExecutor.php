<?php

declare(strict_types=1);

namespace Codedge\Updater\Models;

use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Traits\UseVersionFile;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class UpdateExecutor
{
    use UseVersionFile;

    /**
     * Define the base path where the update should be applied into.
     *
     * @var string
     */
    protected $basePath;

    public function __construct()
    {
        $this->basePath = base_path();
    }

    /**
     * Use the base_path() function to determine the project root folder.
     * This might be not good when running unit tests.
     *
     * @param string $path
     *
     * @return $this
     */
    public function setBasePath(string $path): UpdateExecutor
    {
        $this->basePath = Str::finish($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    /**
     * @param Release $release
     *
     * @return bool
     * @throws Exception
     */
    public function run(Release $release): bool
    {
        if (checkPermissions((new Finder())->in($this->basePath))) {
            $releaseFolder = createFolderFromFile($release->getStoragePath());

            // Move all directories first
            $this->moveFolders($releaseFolder);

            // Now move all the files
            $this->moveFiles($releaseFolder);

            // Delete the folder from the update
            File::deleteDirectory($releaseFolder);

            // Delete the version file
            $this->deleteVersionFile();
            event(new UpdateSucceeded($release));

            return true;
        }

        event(new UpdateFailed($release));

        return false;
    }

    private function moveFiles(string $folder): void
    {
        $files = File::allFiles($folder, true);

        collect($files)->each(function (SplFileInfo $file) {
            if ($file->getRealPath()) {
                File::copy($file->getRealPath(), $this->targetFile($file));
            }
        });
    }

    private function moveFolders(string $folder): void
    {
        $directories = (new Finder())->in($folder)->exclude(config('self-update.exclude_folders'))->directories();

        collect($directories->sort(function (SplFileInfo $a, SplFileInfo $b) {
                return strlen($b->getRealpath()) - strlen($a->getRealpath());
            }))->each(function (SplFileInfo $directory) {
                if (! dirsIntersect(File::directories($directory->getRealPath()), config('self-update.exclude_folders'))) {
                    File::copyDirectory($directory->getRealPath(), $this->targetFolder($directory));
                }

                File::deleteDirectory($directory->getRealPath());
            });
    }

    /**
     * Detect if target path should be project root. Probably not if only unit tests are run, because there is no
     * project root then.
     *
     * @param SplFileInfo $file
     *
     * @return string
     */
    private function targetFile(SplFileInfo $file): string
    {
        if (empty($this->basePath)) {
            return base_path($file->getFilename());
        }

        return $this->basePath . $file->getFilename();
    }

    /**
     * Detect if target path should be project root. Probably not if only unit tests are run, because there is no
     * project root then.
     *
     * @param SplFileInfo $directory
     *
     * @return string
     */
    private function targetFolder(SplFileInfo $directory): string
    {
        if (empty($this->basePath)) {
            return Str::finish(base_path($directory->getRealPath()), DIRECTORY_SEPARATOR) . $directory->getBasename();
        }

        return $this->basePath
               . Str::finish($directory->getRealPath(), DIRECTORY_SEPARATOR)
               . $directory->getBasename();
    }
}
