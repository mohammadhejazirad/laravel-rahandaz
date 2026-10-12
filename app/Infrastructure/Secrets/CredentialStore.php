<?php

declare(strict_types=1);

namespace App\Infrastructure\Secrets;

use App\Models\Credential;
use App\Models\User;
use App\Models\Workspace;

final class CredentialStore
{
    public function __construct(
        private readonly EnvelopeCipher $cipher,
        private readonly MasterKey $masterKey,
    ) {}

    public function store(Workspace $workspace, User $actor, string $type, string $plaintext): Credential
    {
        $aad = $this->aad($workspace->id, $type);

        return Credential::query()->create([
            'workspace_id' => $workspace->id,
            'type' => $type,
            'key_version' => 1,
            'fingerprint' => hash_hmac('sha256', $plaintext, $this->indexKey($workspace->id, $type)),
            'envelope' => $this->cipher->encrypt($plaintext, $aad),
            'created_by' => $actor->id,
        ]);
    }

    public function open(Credential $credential): string
    {
        return $this->cipher->decrypt($credential->envelope, $this->aad($credential->workspace_id, $credential->type));
    }

    private function aad(string $workspaceId, string $type): string
    {
        return $workspaceId.'|credential|'.$type;
    }

    private function indexKey(string $workspaceId, string $type): string
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
