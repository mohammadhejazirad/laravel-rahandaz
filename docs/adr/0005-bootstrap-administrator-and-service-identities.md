# ADR-0005: Separate human administrator from panel service identity

Date: 2026-10-17. Accepted product decision requested explicitly by the owner; VM certification pending.

The owner requires a fixed `rahandaz` Ubuntu SSH/password-sudo administrator and fixed database usernames, with independent random 12-character initial passwords delivered to the operator. Earlier policy used `rahandaz` as a locked service account. Promoting that UID would give compromised panel code administrative access.

Fresh bootstrap therefore creates human `rahandaz` and locked/nologin `rahandaz-svc`. PHP-FPM, queue and scheduler use only the latter. Existing accounts are not promoted or silently migrated. Database role and dedicated Redis ACL usernames remain `rahandaz`; each datastore gets its own password. Encryption keys and web owner credential policy are unchanged.

Credentials are persisted privately before mutation, preserved across reruns and delivered through a root-only file or deliberate direct TTY display. No secret response is added to the web application. Password rotation must retain usernames and coordinate consumers, service health and recovery; database/Redis rotation UI is still pending.

UFW activation is explicit, preserves existing rules, discovers SSH ports and requires a second connection with independent timed disable recovery. Active third-party firewalls require separate adoption. No SSH policy is weakened. Operations Engine remains disabled; legacy account action identifiers now target the restricted service identity, never the human administrator.

See `docs/INSTALLER_BOOTSTRAP_SECURITY.md` for commands, failure recovery, test scope and outstanding VM acceptance. This ADR supersedes earlier locked/no-sudo statements about the human `rahandaz` account; that restriction now applies to `rahandaz-svc`.

Clarification: ADR-0006 requires panel-enrolled public-key-only SSH; the Ubuntu password is for sudo. Live enrollment and hardening remain pending, and bootstrap access is preserved until a verified second connection and recovery exist.
