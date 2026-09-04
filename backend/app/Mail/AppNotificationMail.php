<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email untuk SEMUA jenis notifikasi in-app (approval_pending,
 * request_rejected, request_completed, legal_expiry, dst) — dikirim dari
 * satu titik yang sama dengan notifikasi in-app, lihat
 * App\Services\NotificationService::notifyUser(). Bukan queued job (lihat
 * catatan di notifyUser()) — dikirim sinkron saat request berlangsung.
 */
class AppNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $notificationMessage,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Notifikasi TMS',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
        );
    }
}
