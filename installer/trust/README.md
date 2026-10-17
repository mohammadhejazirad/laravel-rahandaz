# Release trust root

`rahandaz-release.pub` is the SPKI PEM public key selected with the owner's explicit
approval on 2026-10-17 for Rahandaz release signing. The matching private key is
stored outside this repository and in the GitHub `release-signing` environment
secret, never in source control or runtime artifacts.

Public-key file SHA-256:
`7e2589449bce2c3bea617494784f22f211a74ec1d419ad232213e165c8160d09`

A key downloaded beside an artifact is not an independent trust root. Compare
this fingerprint through an independently trusted maintainer channel. The
version-specific installer embeds the pin and checks the manifest signature and
artifact checksum. A signed prerelease does not certify VM installation.

Do not replace this key with a fixture key or generate a fresh key per build.
Rotation requires an explicit trust migration and preservation of recovery for
previously signed releases. Tests generate ephemeral keys outside this directory.

`sury-php.asc` and `sury-php.fingerprint` are the DEB.SURY.ORG archive key used only
to pin `php8.4-*`. They do not authorize a Rahandaz release artifact.
