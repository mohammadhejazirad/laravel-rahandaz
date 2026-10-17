# ADR-0006: Panel-enrolled public-key-only SSH

Date: 2026-10-17. Product policy accepted at the owner's request; live implementation/certification incomplete.

The fixed Ubuntu administrator `rahandaz` uses a password for sudo, and public keys for SSH. Keys are generated on the user's device; only the public key is enrolled through the Laravel management panel. Private keys and passphrases never enter the panel. The application service remains a separate locked, nonprivileged UID.

Ordinary management flows use authenticated, authorized, audited panel operations through the worker and restricted helper. An administrator with SSH and unrestricted password-sudo can still operate outside the panel; this architecture does not claim to prevent that.

Existing bootstrap access must survive until actual key-authenticated second-session access and provider-console recovery are verified. Only then may password/keyboard-interactive SSH and root login be disabled, with whole-config/effective-config validation, safe reload and independent rollback. A client boolean or sandbox fixture is not connection evidence. Secure onboarding remains incomplete while these checks are pending.

A candidate account-scoped OpenSSH template is supplied for parser tests, not automatic activation. Full Laravel enrollment, proof lifecycle and live helper remain outstanding. See `docs/SSH_ACCESS_POLICY.md` for the required workflow and acceptance tests. This decision clarifies ADR-0005: the Ubuntu password is a sudo credential, not an SSH credential.
