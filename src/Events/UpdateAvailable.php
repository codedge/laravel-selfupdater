<?php

namespace Codedge\Updater\Events;

class UpdateAvailable
{
    /**
     * @var string
     */
    protected $newVersion;

    public function __construct(string $newVersion)
    {
        $this->newVersion = $newVersion;
    }

    /**
     * Get the new version.
     *
     * @return string
     */
    public function getVersionAvailable(): string
    {
        return $this->newVersion;
    }
}
