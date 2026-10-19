# Interactive Ubuntu prerequisite installer — contract v1

Entry point: `installer/bin/rahandaz-prerequisites`. Implementation: `installer/lib/prerequisites.sh`.
This is the local operator's prerequisite phase, before application installation. It does not
require an application artifact because it does not install application code. It does not bypass
the signed-release gate of `rahandaz-install`, enable the operations engine, migrate a database,
create an application key, change SSH/firewall/DNS, or reboot the server.

For current server selection, prefer **Ubuntu Server 22.04 LTS or 24.04 LTS (amd64)**.
**Ubuntu 26.04 is experimental and is not currently recommended for installing Rahandaz**, especially
on Iranian servers: compatibility assessment is incomplete and verified mirror choices are more
limited. Successful container tests or one reachable mirror do not certify complete installation.
Reconsider this recommendation after VM and repository/mirror acceptance. The recommendation for
22.04/24.04 is not a claim of production certification.

The installer still recognizes Ubuntu Server **22.04 (jammy), 24.04 (noble), 26.04 (resolute), amd64**
as test targets; 26.04 remains in the test matrix.
Other distributions, Ubuntu versions and architectures fail closed. A target is not a claim of VM
certification. Production is native; unmarked containers are rejected for apply.
All Bash sources in the project must contain English/ASCII text only, including `.sh`, `.bash`
and extensionless Bash entry points. Persian text is forbidden in prompts, messages, errors,
warnings, logs, help, comments and embedded literals during installation, updates, recovery,
operations and tests. Persian web UI copy belongs in HTML templates or translation resources,
never in Bash. This rule is enforced by `test_all_bash_sources_are_ascii` in
`installer/tests/prerequisites_test.py`.
For example: `The server was detected in Iran. Configure an Iranian Ubuntu mirror?`

## Local use

Run from a reviewed complete repository or verified release, not a raw unverified download:

```bash
bash installer/bin/rahandaz-prerequisites --plan
sudo bash installer/bin/rahandaz-prerequisites
```

Interactive apply is the default. It displays OS/version, architecture, kernel, CPU model and
available logical CPUs, total/available RAM, swap, filesystem capacity/free space for `/`, `/var`,
`/tmp`, load and PID 1. CPU capacity is a logical CPU count, not a performance benchmark. It asks
for confirmation of this report **before** asking whether the server is in Iran and Iranian Ubuntu
mirrors should be used (`y`/`n`). A final package-plan confirmation precedes any state/APT mutation.
EOF, invalid input, or a negative confirmation never silently approves an action.

The read-only `--plan` mode does not prompt, download or create state. For explicit automation:

```bash
sudo bash installer/bin/rahandaz-prerequisites --yes --iranian-mirrors yes
sudo bash installer/bin/rahandaz-prerequisites --yes --iranian-mirrors no
```

`--yes` requires an explicit mirror policy. It does not approve recovery of unrelated pending
packages or activation of pre-existing inactive services. These need interactive approval;
package recovery can also be explicitly authorized with `--approve-package-repair`.

The secure Ubuntu base must already contain APT/dpkg, flock, gpgv, the Ubuntu archive keyring and
CA certificates. A missing trust root is not repaired with insecure downloads. The script installs
or verifies curl, gnupg, Python 3, ACL tools and iproute2 before repository key parsing and runtime
installation. At least 1 GiB RAM and 5 GiB free on each relevant filesystem are required; the
project's target profile remains 2 GiB RAM. Container `/proc` can show host memory; the lab has its
own Docker memory limit and is not proof of VPS resource capacity.

## Optional project browser policy

Google Chrome / Chrome for Testing is not a control-plane dependency. Initial installation,
prerequisite setup, resume, repair and panel updates must not prompt for, download or install it.
A project using `spatie/browsershot` or `puppeteer` for PDF/PNG output may need a compatible
headless browser and project runtime; this does not make Chrome, Node.js or npm panel prerequisites.
The intended installation path is an explicit request from the panel software catalog GUI,
through the Operations Engine with authorization, audit and real rendering verification.
Existing project browsers must be preserved. The GUI operation is not certified or enabled by
this documentation. The full installer no longer calls Chrome installation. Legacy `--install-chrome yes` fails
before mutation with `CHROME_PANEL_ONLY`; `no` is a compatibility no-op. The earlier catalog request-only path does not implement installation/render verification;
the Chrome button is now disabled rather than submitting a guaranteed-failure job.
The separate narrow software capability installs only htop/jq/FFmpeg/Unzip; it does not enable Chrome or the general executor. A copyable executable
path is exposed only after successful, trusted worker verification evidence. See the policy and
acceptance criteria in [INSTALLATION.md](INSTALLATION.md#مرورگر-سمت-سرور-فقط-برای-پروژهها).

## Installed prerequisites

- Nginx, PostgreSQL and its client, Redis server.
- PHP 8.4 CLI/FPM and the existing allowlisted panel extensions. Binaries are `/usr/bin/php8.4`
  and `/usr/sbin/php-fpm8.4`. The distribution PHP-FPM service is not activated by this phase.
- Node.js, npm, Composer, Docker and application build tools are not installed for the panel.

The distribution's PostgreSQL/Redis/Nginx versions follow that Ubuntu's authenticated package
indexes; this script does not promise identical majors across Ubuntu releases. Existing
PostgreSQL clusters without the distribution metapackage cause a stop before introducing another
major. Existing PHP/phar binaries with an automatic global alternatives selection also cause an early stop
if PHP 8.4 is not already installed: its selection must be reviewed/protected by the operator.
The script never invokes update-alternatives to switch a user's global PHP runtime.

## Mirrors and trust

APT commands use installer-scoped source files, index lists and package archives under
`/var/lib/rahandaz-prerequisites-apt-v1`. Existing `/etc/apt` files are not replaced or disabled.
An unreachable external source therefore does not participate in this installer's APT transactions.
Normal system APT commands continue to use the operator's existing sources; this is not a
system-wide mirror migration.

Official mode uses the official archive/security sources. Iranian mode tries cataloged Liara,
Arvan and IranServer in order, only for their declared codenames. Chabokan was disabled on
2026-10-17 after signed security indexes dated March 2026 were observed. Liara remains the first
choice: jammy/noble moving metadata matched the official reference during that review; Arvan is
the next fallback. This is a practical preference, not a universal speed ranking for Iranian VPSs. It does not silently
fall back to an international archive. Each attempt uses APT's Ubuntu-keyring signature checking,
HTTPS certificate verification and strict update error handling for base, updates and security.
Moving security metadata must be at most 7 days old, updates metadata at most 14 days old;
future metadata beyond 5 minutes is rejected. The static base release has no age cap. These are
bounded-age checks, not evidence that an unreachable upstream has no newer metadata.

PHP remains on the separately fingerprint-pinned repository defined in `php-runtime.sh`; Iranian
Ubuntu mirrors do not replace it. Failure to access/verify that repository stops runtime installation.
The scoped PHP policy also permits `php-common`, required by PHP 8.4 on all three targets, and
`libgd3` on jammy only, required by the GD extension. These named exceptions were identified by
real APT solver failures; all other non-`php8.4-*` packages from that origin remain blocked. This
same named exception is written by the full installer pin in `php-runtime.sh`.
No PHP mirror, download CDN, DNS change, alternative CA or disabled TLS/signature verification is
invented. Full restricted-network installation still requires the separately planned artifact/PHP
mirror work.

The sources are scoped to the prerequisite phase, not a permanent security-update policy. The
operator must retain working trusted system sources and a reachable PHP source/security-update
process before production acceptance. Coordinating this with the signed full installer is a
separate acceptance step.

## Recovery, locks and existing services

- A root-owned locked state directory is `/var/lib/rahandaz-prerequisites-v1`; symlink/writable
  directory paths are rejected. An audit log and last status are written with private permissions.
- Every run re-queries actual dpkg status. Only `install ok installed` is skipped. A status marker
  never substitutes for package or service verification. APT's archive cache is retained for reruns;
  a partially downloaded package is reused according to APT's normal integrity/resume behavior.
- APT waits for dpkg locks up to 120 seconds; APT retries network acquisition three times.
  Lock files are never deleted and competing package-manager processes are never killed.
- Broken/unconfigured system packages require explicit recovery approval. Recovery uses
  `dpkg --configure -a` and simulated then real `apt-get --fix-broken install --no-remove`.
  Failed repair stays failed. No broad upgrade, purge or package removal is requested. Required
  dependency changes are still possible; APT prints the simulated transaction before applying it.
- During package transactions an exact installer-owned `policy-rc.d` prevents standard package
  maintainer scripts from automatically starting/restarting services. The policy is cleaned up on
  normal exit/error/signals; an exact policy left by a hard interruption is reclaimed on rerun.
  An external policy is preserved; an unfamiliar policy causes a conflict instead of being replaced.
- Package conffiles keep local versions. Existing active services are not restarted and their
  enable-state is preserved. Starting/enabling an existing inactive service requires approval.
  Masked services and occupied expected ports fail without unmasking or stopping anything.
- Newly installed services start only after configuration/binary validation. Health includes actual
  PostgreSQL cluster readiness, Redis loopback PONG and a loopback HTTP response from Nginx.
  External Redis authentication or nonstandard web/DB configuration can require operator action;
  the script does not overwrite it to make a check pass.

Rerun the same command after fixing a reported failure. Do not delete package-manager locks or the
state directory. If a hard interruption leaves the installer-owned policy in place, rerun this script
to recover it; unfamiliar policies require review. There is no automatic package rollback or database
restore. A prerequisite success does not certify application health, end-to-end operations or reboot.

Exit codes: `0` = plan/help or prerequisites verified on systemd; `1` = failure/cancellation;
`2` = invalid arguments; `10` = package verification in the marked container lab, services require VM;
`130`/`143` = interrupted/terminated. Always read the result text, not exit 0 alone.

The later signed application installer still requires release verification and explicit
`--acknowledge-existing` values for these prerequisites. It configures dedicated panel resources;
this script does not create a database/role, configure queue/cache instances or publish the panel.
No public curl command is provided until an actual signed release and its acceptance gates exist.

## Tests

```bash
python3 installer/tests/prerequisites_test.py
bash tests/infrastructure/ubuntu-lab/scripts/test-prerequisites.sh 22.04 --fixtures
bash tests/infrastructure/ubuntu-lab/scripts/test-prerequisites.sh 24.04 --fixtures
bash tests/infrastructure/ubuntu-lab/scripts/test-prerequisites.sh 26.04 --fixtures
```

`--live` in place of `--fixtures` additionally installs packages and reruns the installer **inside a
disposable lab container only**. It also unpacks the cached ACL package to simulate an interrupted
installation, verifies that unapproved repair is refused, then explicitly repairs and rechecks package
state and preservation of existing Nginx/Redis configs and Ubuntu source files. `--mirrors` only
verifies signed Iranian APT indexes from the current lab network.
 No host path is mounted writable, no host Docker socket is mounted,
and host namespaces/privileged mode are not used. Reports are in the lab's ignored `reports/` directory.
Systemd/service activation, reboot, real VPS resource measurements and restricted Iranian networks
require separate VM/network tests. See `INSTALLER_TEST_MATRIX.md` for actual results.

## مرحله امنیت پس از dependencyها

بسته‌های `sudo` و `ufw` نیز نصب/تأیید می‌شوند؛ نصب بسته UFW به معنی فعال‌سازی آن نیست. بعد از healthcheck واقعی، اجرای تعاملی ادامه به `rahandaz-security --apply` را پیشنهاد می‌کند. مدیر `rahandaz`، سرویس محدود `rahandaz-svc`، نقش PostgreSQL و Redisهای اختصاصی طبق [قرارداد امنیت bootstrap](INSTALLER_BOOTSTRAP_SECURITY.md) ایجاد می‌شوند. در `--yes`، مرحله امنیت صریح و جدا اجرا می‌شود. UFW فعال‌سازی جدا با حفظ SSH و rollback دارد. رمزها فقط فایل خصوصی/TTY و بدون بازتولید در rerun تحویل می‌شوند.
