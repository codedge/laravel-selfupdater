<?php

declare(strict_types=1);

namespace Codedge\Updater\Exceptions;

final class VersionException extends \Exception
{
    public static function versionInstalledNotFound(): VersionException
    {
        return new VersionException('Version installed not found.');
    }
}
