<?php declare(strict_types=1);

namespace Codedge\Updater\Tests\Commands;

use Codedge\Updater\Commands\CheckForUpdate;
use Codedge\Updater\Tests\TestCase;

final class CheckUpdateTest extends TestCase
{
    /** @test */
    public function it_can_run_check_update_command_without_new_version_available(): void
    {
        config(['self-update.version_installed' => 'v3.5']);

        $this->artisan(CheckForUpdate::class)
             ->expectsOutput('There\'s no new version available.')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_can_run_check_update_command_with_new_version_available(): void
    {
        config(['self-update.version_installed' => 'v1.0']);

        $this->artisan(CheckForUpdate::class)
             ->expectsOutput('A new version [v3.5] is available.')
             ->assertExitCode(0);
    }
}
