<?php

declare(strict_types=1);

namespace Codedge\Updater\Notifications;

use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification
{
    public function via(): array
    {
        $notificationChannels = config('self-update.notifications.notifications.'.static::class);

        return array_filter($notificationChannels);
    }
}
