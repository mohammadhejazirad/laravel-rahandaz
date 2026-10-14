# Release trust root

This directory is where a production release public key would live:

`rahandaz-release.pub`

The key is intentionally absent. A checksum downloaded beside an artifact is not a trust root, and this repository does not invent a signature or a key. `installer/bin/rahandaz-install --apply` on `/` refuses to continue until the operator passes a public key they already trust and a manifest signature that verifies with it.

Tests generate an ephemeral key outside this directory. Do not commit that key, and do not replace this note with an example key that verifies a fixture.

`sury-php.asc` and `sury-php.fingerprint` are the DEB.SURY.ORG archive key used only to pin `php8.4-*`. They are not `rahandaz-release.pub` and they do not authorize a Rahandaz release artifact.
