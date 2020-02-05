<?php declare(strict_types=1);

namespace Codedge\Updater\Traits;

use Illuminate\Support\Facades\Storage;

trait UseVersionFile
{
    /**
     * @var string
     */
    protected $versionFile = 'self-updater-new-version';

    /**
     * Check if the file with the new version already exists.
     *
     * @return bool
     */
    protected function versionFileExists(): bool
    {
        return Storage::exists($this->versionFile);
    }

    /**
     * Write the version file.
     *
     * @param $content
     *
     * @return bool
     */
    protected function setVersionFile(string $content): bool
    {
        return Storage::put($this->versionFile, $content);
    }

    /**
     * Get the content of the version file.
     *
     * @return string
     */
    protected function getVersionFile(): string
    {
        return trim(Storage::get($this->versionFile));
    }

    /**
     * Delete the version file.
     *
     * @return bool
     */
    protected function deleteVersionFile(): bool
    {
        return Storage::delete($this->versionFile);
    }
}
