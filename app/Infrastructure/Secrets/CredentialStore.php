<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Domain\Audit\Enums\AuditResult;
use App\Domain\Audit\Enums\CredentialAccessAction;
use App\Models\Credential;
use App\Models\CredentialAccessEvent;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

final class CredentialStore
{
    public function __construct(
        private readonly EnvelopeCipher $cipher,
        private readonly MasterKey $masterKey,
    ) {}

    public function store(Workspace $workspace, User $actor, string $type, string $plaintext, ?string $label = null): Credential
    {
        return Credential::query()->create($this->material($workspace, $actor, $type, $plaintext, $label));
    }

    /**
     * @return array{workspace_id: string, type: string, label: ?string, key_version: int, algorithm: string, fingerprint: string, envelope: array<string, int|string>, created_by: string}
     */
    public function material(Workspace $workspace, User $actor, string $type, string $plaintext, ?string $label = null): array
    {
        return [
            'workspace_id' => $workspace->id,
            'type' => $type,
            'label' => $label,
            'key_version' => 1,
            'algorithm' => EnvelopeCipher::ALGORITHM,
            'fingerprint' => hash_hmac('sha256', $plaintext, $this->indexKey($workspace->id, $type)),
            'envelope' => $this->cipher->encrypt($plaintext, $this->aad($workspace->id, $type)),
            'created_by' => $actor->id,
        ];
    }

    public function open(Credential $credential): string
    {
        return $this->cipher->decrypt($credential->envelope, $this->aad($credential->workspace_id, $credential->type));
    }

    public function openFor(Credential $credential, User $actor): string
    {
        $membership = Membership::query()
            ->where('workspace_id', $credential->workspace_id)
            ->where('user_id', $actor->id)
            ->first();

        if ($membership === null || ! $membership->role->mayDecryptSecrets() || $credential->revoked_at !== null) {
            $this->recordAccess($credential, $actor, AuditResult::Denied);

            throw new AuthorizationException('Credential access is denied.');
        }

        try {
            $plaintext = $this->open($credential);
        } catch (Throwable $exception) {
            $this->recordAccess($credential, $actor, AuditResult::Failure);

            throw $exception;
        }

        $this->recordAccess($credential, $actor, AuditResult::Success);

        return $plaintext;
    }

    private function recordAccess(Credential $credential, User $actor, AuditResult $result): void
    {
        CredentialAccessEvent::query()->create([
            'workspace_id' => $credential->workspace_id,
            'credential_id' => $credential->id,
            'actor_id' => $actor->id,
            'action' => CredentialAccessAction::Decrypt,
            'result' => $result,
            'recorded_at' => now(),
        ]);
    }

    private function aad(int|string $workspaceId, string $type): string
    {
        return $workspaceId.'|credential|'.$type;
    }

    private function indexKey(int|string $workspaceId, string $type): string
    {
        return hash_hkdf(
            'sha256',
            $this->masterKey->bytes(),
            32,
            'rahandaz-blind-index-v1|'.$workspaceId.'|'.$type,
            'rahandaz-credential-index',
        );
    }
}
