<?php

declare(strict_types=1);

namespace App\Infrastructure\Updates;

use App\Domain\Accounts\Enums\MembershipRole;
use App\Domain\Notifications\Enums\NotificationLevel;
use App\Models\Membership;
use App\Models\PanelNotification;
use Throwable;

final class UpdateNotificationService
{
    /**
     * @return array{title: string, body: string, event_key: string, action_path: string, type: string, level: string}
     */
    public function message(string $latest, string $installed, int $recipientId): array
    {
        return [
            'title' => 'نسخه جدید راه‌انداز منتشر شد',
            'body' => 'نسخه '.$latest.' راه‌انداز منتشر شده است. نسخه فعلی شما '.$installed.' است.',
            'event_key' => 'rahandaz-update:'.$latest.':'.$recipientId,
            'action_path' => '/rahandaz/settings/updates',
            'type' => 'rahandaz.update',
            'level' => NotificationLevel::Info->value,
        ];
    }

    public function notifyOwners(string $latest, string $installed): void
    {
        if (preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]{0,62}$/', $latest) !== 1) {
            return;
        }
        try {
            $owners = Membership::query()->where('role', MembershipRole::Owner->value)->get();
        } catch (Throwable) {
            return;
        }
        foreach ($owners as $membership) {
            $message = $this->message($latest, $installed, (int) $membership->user_id);
            if (strlen($message['event_key']) > 160 || strlen($message['body']) > 2000) {
                continue;
            }
            try {
                PanelNotification::query()->firstOrCreate(
                    ['event_key' => $message['event_key']],
                    [
                        'workspace_id' => $membership->workspace_id,
                        'recipient_id' => $membership->user_id,
                        'type' => $message['type'],
                        'level' => $message['level'],
                        'title' => $message['title'],
                        'body' => $message['body'],
                        'action_path' => $message['action_path'],
                    ],
                );
            } catch (Throwable) {
                continue;
            }
        }
    }
}
