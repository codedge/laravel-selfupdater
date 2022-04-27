<?php

declare(strict_types=1);

namespace Codedge\Updater\Contracts;

interface UpdaterContract
{
    /**
     * Get a source type instance.
     */
    public function source(string $name = ''): SourceRepositoryTypeContract;
}
