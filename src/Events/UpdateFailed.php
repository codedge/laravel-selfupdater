<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

class UpdateFailed
{
    /**
     * @var string
     */
    protected $eventName = 'Update failed';

    protected $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->eventName;
    }
}
