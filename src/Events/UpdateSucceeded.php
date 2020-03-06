<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

class UpdateSucceeded
{
    /**
     * @var string
     */
    protected $eventName = 'Update succeeded';

    protected $release;

    /**
     * UpdateFailed constructor.
     *
     * @param $versionUpdatedTo
     */
    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * Get the new version.
     *
     * @return string
     */
    public function getVersionUpdatedTo()
    {
        return $this->release->getVersion();
    }
}
