<?php

declare(strict_types=1);

namespace Codedge\Updater\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

final class InvalidRepositoryException extends Exception
{
    public function report(): void
    {
        Log::error('The vendor and/or name of the repository is invalid.');
    }
}
