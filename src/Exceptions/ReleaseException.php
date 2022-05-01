<?php

declare(strict_types=1);

namespace Codedge\Updater\Exceptions;

final class ReleaseException extends \Exception
{
    public static function noReleaseFound(string $version): self
    {
        $version = $version !== '' ? $version : 'latest version';

        return new self(sprintf('No release found for version "%s". Please check the repository you\'re pulling from', $version));
    }

    public static function cannotExtractDownloadLink(string $pattern): self
    {
        return new self(sprintf('Cannot extract download/release link from source. Pattern "%s" not found.', $pattern));
    }

    public static function archiveFileNotFound(string $path): self
    {
        return new self(sprintf('Archive file "%s" not found.', $path));
    }

    public static function archiveNotAZipFile(string $mimeType): self
    {
        return new self(sprintf('File is not a zip archive. File is "%s"', $mimeType));
    }

    public static function cannotExtractArchiveFile(string $path): self
    {
        return new self(sprintf('Cannot open zip archive "%s"', $path));
    }
}
