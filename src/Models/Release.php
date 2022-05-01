<?php

declare(strict_types=1);

namespace Codedge\Updater\Models;

use Codedge\Updater\Exceptions\ReleaseException;
use Codedge\Updater\Traits\SupportPrivateAccessToken;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

final class Release
{
    use SupportPrivateAccessToken;

    /**
     * Name of release file.
     * Example: release-1.1.zip.
     */
    private ?string $release = null;

    /**
     * Path to download the release to.
     * Example: /tmp/release-1.1.zip.
     */
    private ?string $storagePath = null;

    /**
     * Path where the update should be applied to. Most probably to your base_path() - that's where your
     * current Laravel installation runs.
     */
    private ?Finder $updatePath = null;

    /**
     * The version name.
     * Example: 1.1 or v1.1.
     */
    private ?string $version = null;

    /**
     * Url to download the release from.
     */
    private ?string $downloadUrl = null;

    public function getRelease(): ?string
    {
        return $this->release;
    }

    public function setRelease(string $release): self
    {
        $this->release = $release;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = $storagePath;

        if (!File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 493, true, true);
        }

        return $this;
    }

    /**
     * Update the storage path to include the release name.
     */
    public function updateStoragePath(): self
    {
        if (!empty($this->getRelease())) {
            $this->storagePath = Str::finish($this->storagePath, DIRECTORY_SEPARATOR).$this->getRelease();

            return $this;
        }

        return $this;
    }

    public function getUpdatePath(): ?Finder
    {
        return $this->updatePath;
    }

    /**
     * @param array<string> $excluded
     */
    public function setUpdatePath(string $updatePath, array $excluded = []): self
    {
        $this->updatePath = (new Finder())->in($updatePath)->exclude($excluded);

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function setDownloadUrl(string $downloadUrl): self
    {
        $this->downloadUrl = $downloadUrl;

        return $this;
    }

    public function extract(bool $deleteSource = true): bool
    {
        if (!File::exists($this->getStoragePath())) {
            throw ReleaseException::archiveFileNotFound($this->getStoragePath());
        }

        $extractTo = createFolderFromFile($this->getStoragePath());
        $extension = pathinfo($this->getStoragePath(), PATHINFO_EXTENSION);

        if (preg_match('/[zZ]ip/', $extension)) {
            $extracted = $this->extractZip($extractTo);

            // Create the final release directory
            if ($extracted && $this->createReleaseFolder() && $deleteSource) {
                File::delete($this->getStoragePath());
            }

            return true;
        } else {
            throw ReleaseException::archiveNotAZipFile(File::mimeType($this->getStoragePath()));
        }
    }

    protected function extractZip(string $extractTo): bool
    {
        $zip = new \ZipArchive();
        $res = $zip->open($this->getStoragePath());

        if ($res !== true) {
            throw ReleaseException::cannotExtractArchiveFile($this->getStoragePath());
        }

        $extracted = $zip->extractTo($extractTo);
        $zip->close();

        return $extracted;
    }

    public function download(): Response
    {
        if (empty($this->getStoragePath())) {
            throw new Exception('No storage path set.');
        }

        $headers = [];

        if ($this->hasAccessToken()) {
            $headers = [
                'Authorization' => $this->getAccessToken(),
            ];
        }

        return Http::withHeaders($headers)
                   ->withOptions([
                       'sink' => $this->getStoragePath(),
                   ])
                   ->get($this->getDownloadUrl());
    }

    /**
     * Create a release sub-folder inside the storage dir.
     * Example: /tmp/release-1.2/.
     */
    protected function createReleaseFolder(): bool
    {
        $folders = File::directories(createFolderFromFile($this->getStoragePath()));

        if (count($folders) === 1) {
            // Only one sub-folder inside extracted directory
            $moved = File::moveDirectory(
                $folders[0],
                createFolderFromFile($this->getStoragePath()).now()->toDateString()
            );

            if (!$moved) {
                return false;
            }

            File::moveDirectory(
                createFolderFromFile($this->getStoragePath()).now()->toDateString(),
                createFolderFromFile($this->getStoragePath()),
                true
            );
        }

        File::delete($this->getStoragePath());

        return true;
    }

    /**
     * Check if the release file has already been downloaded.
     */
    public function isSourceAlreadyFetched(): bool
    {
        $extractionDir = createFolderFromFile($this->getStoragePath());

        // Check if source archive is (probably) deleted but extracted folder is there.
        if (!File::exists($this->getStoragePath())
            && File::exists($extractionDir)) {
            return true;
        }

        // Check if source archive is there but not extracted
        if (File::exists($this->getStoragePath())
            && !File::exists($extractionDir)) {
            return true;
        }

        // Check if source archive and folder exists
        if (File::exists($this->getStoragePath())
            && File::exists($extractionDir)) {
            return true;
        }

        return false;
    }
}
