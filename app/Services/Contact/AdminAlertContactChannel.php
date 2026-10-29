<?php

namespace App\Services\Contact;

use App\Contracts\ContactMessageChannel;
use App\Services\NotificationService;

/**
 * Delivers a public enquiry as an in-app alert to the administrator accounts —
 * the same routing used for other admin-only incidents (account recovery,
 * gateway outages). Kept behind the ContactMessageChannel contract so a future
 * e-mail channel can be added alongside it without touching the controller.
 */
final class AdminAlertContactChannel implements ContactMessageChannel
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function deliver(ContactMessage $message): void
    {
        $admins = $this->notifications->administrators();

        if ($admins === []) {
            return;
        }

        $this->notifications->forUsers(
            $admins,
            'New contact enquiry: '.$message->headline(),
            str($message->toPlainText())->limit(1000)->toString(),
            '/contact',
            'contact',
        );
    }
}
