<?php

declare(strict_types=1);

namespace Codedge\Updater\Tests\Notifications;

use Codedge\Updater\Events\UpdateFailed;
use Codedge\Updater\Models\Release;
use Codedge\Updater\Notifications\Notifiable;
use Codedge\Updater\Notifications\Notifications\UpdateFailed as UpdateFailedNotification;
use Codedge\Updater\Tests\TestCase;
use Illuminate\Support\Facades\Notification;

class EventHandlerTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** @test */
    public function it_will_send_a_notification_by_default_when_update_failed()
    {
        $this->fireUpdateFailedEvent();

        Notification::assertSentTo(new Notifiable(), UpdateFailedNotification::class);
    }

    /**
     * @test
     *
     * @dataProvider channelProvider
     *
     * @param array $expectedChannels
     */
    public function it_will_send_a_notification_via_the_configured_notification_channels(array $expectedChannels)
    {
        config()->set('self-update.notifications.notifications.'.UpdateFailedNotification::class, $expectedChannels);

        $this->fireUpdateFailedEvent();

        Notification::assertSentTo(new Notifiable(), UpdateFailedNotification::class, function ($notification, $usedChannels) use ($expectedChannels) {
            return $expectedChannels == $usedChannels;
        });
    }

    public function channelProvider()
    {
        return [
            [[]],
            [['mail']],
        ];
    }

    protected function fireUpdateFailedEvent()
    {
        $release = resolve(Release::class);

        event(new UpdateFailed($release));
    }
}
