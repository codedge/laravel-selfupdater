<?php declare(strict_types=1);

namespace Codedge\Updater\Models;

use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Events\UpdateSucceeded;
use Codedge\Updater\Traits\UseVersionFile;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

final class UpdateExecutor
{
    use UseVersionFile;

    private $useBasePath = true;

    /**
     * Use the base_path() function to determine the project root folder.
     * This might be not good when running unit tests.
     *
     * @param bool $flag
     *
     * @return $this
     */
    public function setUseBasePath(bool $flag = true): UpdateExecutor
    {
        $this->useBasePath = $flag;

        return $this;
    }

    /**
     * @param Release $release
     *
     * @return bool
     * @throws Exception
     */
    public function run(Release $release)
    {
        $finder = $release->getUpdatePath();

        if ($finder && checkPermissions($finder)) {

            // Move all directories first
            collect($finder->directories()
                           ->sort(function (SplFileInfo $a, SplFileInfo $b) {
                               return strlen($b->getRealpath()) - strlen($a->getRealpath());
                           }))->each(function (SplFileInfo $directory) {
                if (! dirsIntersect(
                    File::directories($directory->getRealPath()), config('self-update.exclude_folders'))
                ) {
                    File::copyDirectory(
                        $directory->getRealPath(),
                        $this->targetFolder($directory->getRelativePath()).DIRECTORY_SEPARATOR.$directory->getBasename()
                    );
                }

                File::deleteDirectory($directory->getRealPath());
            });

            // Now move all the files left in the main directory
            collect($finder->files())->each(function (SplFileInfo $file) {
                if ($file->getRealPath()) {
                    File::copy($file->getRealPath(), base_path($file->getFilename()));
                }
            });

            $iterator = $finder->getIterator();
            $iterator->rewind();
            File::deleteDirectory($iterator->current());

            $this->deleteVersionFile();
            event(new UpdateSucceeded($release));

            return true;
        }

        event(new UpdateFailed($release));

        return false;
    }

    private function targetFolder(string $folder): string
    {
        if($this->useBasePath) {
            return base_path($folder);
        }

        return $folder;
    }
}
