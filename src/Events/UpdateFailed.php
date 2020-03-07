<?php

namespace Codedge\Updater\Events;

use Codedge\Updater\Models\Release;

class UpdateFailed
{
    protected $release;

    public function __construct(Release $release)
    {
        $this->release = $release;
    }
}
