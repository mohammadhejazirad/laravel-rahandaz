<?php

declare(strict_types=1);

namespace App\Domain\Git;

use App\Domain\Git\Enums\WebhookProcessingState;
use App\Models\GitWebhook;
use App\Models\GitWebhookDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class WebhookDeliveryRecorder
{
    public function record(
        GitWebhook $webhook,
        string $deliveryId,
        string $eventName,
        string $payload,
        bool $signatureValid,
    ): GitWebhookDelivery {
        $existing = $this->find($webhook, $deliveryId);

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn (): GitWebhookDelivery => GitWebhookDelivery::query()->create([
                'workspace_id' => $webhook->workspace_id,
                'git_webhook_id' => $webhook->id,
                'delivery_id' => $deliveryId,
                'event_name' => $eventName,
                'payload_digest' => hash('sha256', $payload),
                'received_at' => now(),
                'verified_at' => $signatureValid ? now() : null,
                'processing_state' => $signatureValid
                    ? WebhookProcessingState::Received
                    : WebhookProcessingState::Rejected,
                'signature_valid' => $signatureValid,
                'failure_code' => $signatureValid ? null : 'SIGNATURE_INVALID',
            ]));
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                $raced = $this->find($webhook, $deliveryId);

                if ($raced !== null) {
                    return $raced;
                }
            }

            throw $exception;
        }
    }

    private function find(GitWebhook $webhook, string $deliveryId): ?GitWebhookDelivery
    {
        return GitWebhookDelivery::query()
            ->where('git_webhook_id', $webhook->id)
            ->where('delivery_id', $deliveryId)
            ->first();
    }
}
