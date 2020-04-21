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
    public function setBasePath(string $path): self
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
        $files = (new Finder())->in($folder)
                               ->exclude(config('self-update.exclude_folders'))
                               ->ignoreDotFiles(false)
                               ->files();

        collect($files)->each(function (SplFileInfo $file) {
            if ($file->getRealPath()) {
                File::copy(
                    $file->getRealPath(), Str::finish($this->basePath, DIRECTORY_SEPARATOR).$file->getFilename()
                );
            }
        });
    }

    private function moveFolders(string $folder): void
    {
        $directories = (new Finder())->in($folder)->exclude(config('self-update.exclude_folders'))->directories();

        $sorted = collect($directories->sort(function (SplFileInfo $a, SplFileInfo $b) {
            return strlen($b->getRealpath()) - strlen($a->getRealpath());
        }));

        $sorted->each(function (SplFileInfo $directory) {
            if (! dirsIntersect(File::directories($directory->getRealPath()), config('self-update.exclude_folders'))) {
                File::copyDirectory(
                    $directory->getRealPath(),
                    Str::finish($this->basePath, DIRECTORY_SEPARATOR).Str::finish($directory->getRelativePath(), DIRECTORY_SEPARATOR).$directory->getBasename()
                );
            }

            File::deleteDirectory($directory->getRealPath());
        });
    }
}
