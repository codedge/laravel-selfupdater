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
}
