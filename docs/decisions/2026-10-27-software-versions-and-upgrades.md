# Software version choice and same-line upgrades

The owner asked to choose an installable version, for example PHP, and to update each installed item from its own button until the real job finishes.

The signed catalog stays the allowlist. PHP is one product with fixed keys `php82`, `php83`, `php` and `php85`. The browser submits those keys. It does not submit an apt name, version string or URL. Initial setup still offers only htop, jq, FFmpeg and Unzip.

`software.install` still uses `--no-upgrade` and still refuses removals. A new job type, `software.upgrade`, updates one installed apt key. The helper may refresh apt indexes, then upgrade only that package and the siblings named by its `upgrade_prefix`. Removals and packages outside that scope are refused. PostgreSQL, MySQL and MariaDB are not upgradeable here because a metapackage move can change the major version. Node, MongoDB and Chrome stay on their pinned archives.

If a PHP package script changes the `/usr/bin/php` alternative, the helper sets it back to the previous `/usr/bin/phpX.Y` target. It does not select a new system default and it does not remove other PHP versions.

The installed page lists verified catalog items. The matching button stays in the loading state until that job is terminal. A queued request is not a successful upgrade. This contract is covered by helper unit tests and the PHP feature test. It has not been executed against the live KVM guest, so it is not a certified Ubuntu result.
