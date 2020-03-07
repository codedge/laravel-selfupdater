<?php

namespace Codedge\Updater\Events;

use SplFileInfo;

class HasWrongPermissions
{
    /**
     * @var string
     */
    protected $eventName = 'Update failed';

    /**
     * @var SplFileInfo
     */
    protected $file;

    public function __construct(SplFileInfo $file)
    {
        $this->file = $file;
    }
}
