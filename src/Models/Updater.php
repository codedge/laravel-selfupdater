<?php declare(strict_types=1);

namespace Codedge\Updater\Models;

use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Traits\UseVersionFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

final class UpdateExecutor
{
    use UseVersionFile;

    /**
     * @param \Codedge\Updater\Models\Release $release
     *
     * @return bool
     * @throws \Exception
     */
    public function run(Release $release)
    {
        $release->setUpdatePath(base_path(), config('self-update.exclude_folders'));
        $sourcePath = createFolderFromFile($release->getStoragePath());

        if (checkPermissions($release->getUpdatePath())) {
            // Move all directories first
            collect((new Finder())->in($sourcePath)
                                  ->exclude(config('self-update.exclude_folders'))
                                  ->directories()
                                  ->sort(function ($a, $b) {
                                      return strlen($b->getRealpath()) - strlen($a->getRealpath());
                                  }))->each(function (/** @var \SplFileInfo $directory */ $directory) {
                if (! dirsIntersect(
                    File::directories($directory->getRealPath()), config('self-update.exclude_folders'))
                ) {
                    File::copyDirectory(
                        $directory->getRealPath(),
                        base_path($directory->getRelativePath()).DIRECTORY_SEPARATOR.$directory->getBasename()
                    );
                }

                File::deleteDirectory($directory->getRealPath());
            });

            // Now move all the files left in the main directory
            collect(File::allFiles($sourcePath, true))->each(function ($file) { /* @var \SplFileInfo $file */
                if ($file->getRealPath()) {
                    File::copy($file->getRealPath(), base_path($file->getFilename()));
                }
            });

            File::deleteDirectory($sourcePath);
            $this->deleteVersionFile();
            event(new UpdateSucceeded($release));

            return true;
        }

        event(new UpdateFailed($release));

        return false;
    }
}
