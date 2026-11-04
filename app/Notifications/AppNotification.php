<?php

namespace App\Notifications;

use App\Support\Notify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Basis notifikasi PropertyMe: satu sumber judul/isi/link untuk in-app & email.
 * Dikirim lewat antrean (cron cPanel menjalankan queue:work --stop-when-empty tiap menit).
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function url(): string;

    protected function icon(): string
    {
        return 'bi-bell';
    }

    /** success | warning | danger | info */
    protected function tone(): string
    {
        return 'info';
    }

    protected function actionText(): string
    {
        return __('Buka PropertyMe');
    }

    public function via(object $notifiable): array
    {
        return Notify::channels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'icon' => $this->icon(),
            'tone' => $this->tone(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' · PropertyMe')
            ->greeting(__('Halo :name,', ['name' => strtok($notifiable->name, ' ')]))
            ->line($this->body())
            ->action($this->actionText(), $this->url())
            ->salutation('Salam, PropertyMe');
    }
}
