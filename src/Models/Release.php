<?php

declare(strict_types=1);

namespace Codedge\Updater\Models;

use Codedge\Updater\Traits\SupportPrivateAccessToken;
use Exception;
use GuzzleHttp\ClientInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Finder\Finder;

final class Release
{
    use SupportPrivateAccessToken;

    /**
     * Name of release file.
     * Example: release-1.1.zip.
     *
     * @var string
     */
    private $release;

    /**
     * Path to download the release to.
     * Example: /tmp/release-1.1.zip.
     *
     * @var string
     */
    private $storagePath;

    /**
     * Path where the update should be applied to. Most probably to your base_path() - that's where your
     * current Laravel installation runs.
     *
     * @var Finder
     */
    private $updatePath;

    /**
     * The version name.
     * Example: 1.1 or v1.1.
     *
     * @var string
     */
    private $version;

    /**
     * Url to download the release from.
     *
     * @var string
     */
    private $downloadUrl;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @return string
     */
    public function getRelease(): ?string
    {
        return $this->release;
    }

    /**
     * @param string $release
     *
     * @return Release
     */
    public function setRelease(string $release): self
    {
        $this->release = $release;

        return $this;
    }

    /**
     * @return string
     */
    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    /**
     * @param string $storagePath
     *
     * @return Release
     */
    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = $storagePath;

        if (! $this->filesystem->exists($this->storagePath)) {
            $this->filesystem->makeDirectory($this->storagePath, 493, true, true);
        }

        return $this;
    }

    /**
     * Update the storage path to include the release name.
     *
     * @return Release
     */
    public function updateStoragePath(): self
    {
        if (! empty($this->getRelease())) {
            $this->storagePath = Str::finish($this->storagePath, DIRECTORY_SEPARATOR).$this->getRelease();

            return $this;
        }

        return $this;
    }

    /**
     * @return Finder
     */
    public function getUpdatePath(): ?Finder
    {
        return $this->updatePath;
    }

    /**
     * @param string $updatePath
     * @param array $excluded
     *
     * @return Release
     */
    public function setUpdatePath(string $updatePath, array $excluded = []): self
    {
        $this->updatePath = (new Finder())->in($updatePath)->exclude($excluded);

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * @param string $version
     *
     * @return Release
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    /**
     * @param string $downloadUrl
     *
     * @return Release
     */
    public function setDownloadUrl(string $downloadUrl): self
    {
        $this->downloadUrl = $downloadUrl;

        return $this;
    }

    public function extract(bool $deleteSource = true): bool
    {
        $extractTo = createFolderFromFile($this->getStoragePath());
        $extension = pathinfo($this->getStoragePath(), PATHINFO_EXTENSION);

        if (preg_match('/[zZ]ip/', $extension)) {
            $extracted = $this->extractZip($extractTo);

            // Create the final release directory
            if ($extracted && $this->createReleaseFolder() && $deleteSource) {
                $this->filesystem->delete($this->storagePath);
            }

            return true;
        } else {
            throw new Exception('File is not a zip archive. File is '.$this->filesystem->mimeType($this->getStoragePath()).'.');
        }
    }

    protected function extractZip(string $extractTo): bool
    {
        $zip = new \ZipArchive();
        $res = $zip->open($this->getStoragePath());

        if ($res !== true) {
            throw new Exception("Cannot open zip archive [{$this->getStoragePath()}]. Error: $res");
        }

        $extracted = $zip->extractTo($extractTo);
        $zip->close();

        return $extracted;
    }

    public function download(ClientInterface $client): ResponseInterface
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

        return $client->request(
            'GET',
            $this->getDownloadUrl(),
            [
                'sink' => $this->getStoragePath(),
                'headers' => $headers,
            ]
        );
    }

    /**
     * Create a release sub-folder inside the storage dir.
     * Example: /tmp/release-1.2/.
     *
     * @return bool
     */
    protected function createReleaseFolder(): bool
    {
        $folders = $this->filesystem->directories(createFolderFromFile($this->getStoragePath()));

        if (count($folders) === 1) {
            // Only one sub-folder inside extracted directory
            $moved = $this->filesystem->moveDirectory(
                $folders[0], createFolderFromFile($this->getStoragePath()).now()->toDateString()
            );

            if (! $moved) {
                return false;
            }

            $this->filesystem->moveDirectory(
                createFolderFromFile($this->getStoragePath()).now()->toDateString(),
                createFolderFromFile($this->getStoragePath())
            );
        }

        $this->filesystem->delete($this->getStoragePath());

        return true;
    }

    /**
     * Check if the release file has already been downloaded.
     *
     * @return bool
     */
    public function isSourceAlreadyFetched(): bool
    {
        $extractionDir = createFolderFromFile($this->getStoragePath());

        // Check if source archive is (probably) deleted but extracted folder is there.
        if (! $this->filesystem->exists($this->getStoragePath())
            && $this->filesystem->exists($extractionDir)) {
            return true;
        }

        // Check if source archive is there but not extracted
        if ($this->filesystem->exists($this->getStoragePath())
            && ! $this->filesystem->exists($extractionDir)) {
            return true;
        }

        // Check if source archive and folder exists
        if ($this->filesystem->exists($this->getStoragePath())
            && $this->filesystem->exists($extractionDir)) {
            return true;
        }

        return false;
    }
}
