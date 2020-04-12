<?php

declare(strict_types=1);

namespace Codedge\Updater\Notifications\Notifications;

use Codedge\Updater\Events\UpdateSucceeded as UpdateSucceededEvent;
use Codedge\Updater\Notifications\BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

final class UpdateSucceeded extends BaseNotification
{
    /**
     * @var UpdateSucceededEvent
     */
    protected $event;

    public function toMail(): MailMessage
    {
        return (new MailMessage())
            ->from(config('self-update.notifications.mail.from.address', config('mail.from.address')), config('self-update.notifications.mail.from.name', config('mail.from.name')))
            ->subject(config('app.name').': Update succeeded');
    }

    public function setEvent(UpdateSucceededEvent $event)
    {
        $this->event = $event;

        return $this;
    }
}
