<?php

declare(strict_types=1);

namespace Codedge\Updater\Exceptions;

final class ReleaseException extends \Exception
{
    public static function noReleaseFound(string $version): ReleaseException
    {
        $version = $version !== '' ? $version : 'latest version';

       return new ReleaseException(sprintf('No release found for version "%s". Please check the repository you\'re pulling from', $version));
    }
}
