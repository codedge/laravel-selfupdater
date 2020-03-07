<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

class UpdateSucceeded
{
    protected $release;

    /**
     * UpdateFailed constructor.
     *
     * @param Release $release
     */
    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    /**
     * Get the new version.
     *
     * @return string
     */
    public function getVersionUpdatedTo(): string
    {
        return $this->release->getVersion();
    }
}
