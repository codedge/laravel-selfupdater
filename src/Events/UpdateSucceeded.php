<?php

declare(strict_types=1);

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

class UpdateSucceeded
{
    protected Release $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    public function getVersionUpdatedTo(): ?string
    {
        return $this->release->getVersion();
    }
}
