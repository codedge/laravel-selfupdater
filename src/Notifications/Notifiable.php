<?php

declare(strict_types=1);

namespace Codedge\Updater\Notifications;

use Illuminate\Notifications\Notifiable as NotifiableTrait;

final class Notifiable
{
    use NotifiableTrait;

    public function routeNotificationForMail()
    {
        return config('backup.notifications.mail.to');
    }

    public function getKey()
    {
        return 1;
    }
}
