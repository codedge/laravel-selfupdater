<?php

namespace Codedge\Updater\Commands;

use Codedge\Updater\UpdaterManager;
use Illuminate\Console\Command;

class CheckForUpdate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'updater:check-for-update';

    /**
     * @var string
     */
    protected $description = 'Check if a new update is available.';

    /**
     * @var UpdaterManager
     */
    protected $updater;

    /**
     * CheckForUpdate constructor.
     *
     * @param UpdaterManager $updater
     */
    public function __construct(UpdaterManager $updater)
    {
        $this->updater = $updater;
    }

    /**
     * Execute the command.
     */
    public function handle()
    {
        $isAvail = $this->updater->source()->isNewVersionAvailable();

        if ($isAvail) {
            $newVersion = $this->updater->source()->getVersionAvailable();
            $this->info('A new version ['.$newVersion.'] is available.');
        } else {
            $this->comment('There\'s no new version available.');
        }
    }
}
