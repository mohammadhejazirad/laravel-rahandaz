# Categorized software and monitoring verification — 2026-10-19

This report extends the earlier four-tool `SOFTWARE_SETUP_TEST_REPORT_2026_10_19.md`. Scope: eighteen fixed catalog entries, real per-package outcomes/counts, terminal loading/Toast, versioned runtime paths, and read-only live CPU/RAM/disk/network. General operations remain disabled. This is beta validation, not stable v1 or three-VM certification.

## Local checks executed

PHP suite: 219 tests / 4,208 assertions passed before release, with disposable PostgreSQL `rahandaz_test` and isolated loopback Redis. Subsequent focused page/software tests also passed. Cases include real-result gating, cross-job evidence rejection, authorization revocation, partial batch counts, deduplicated events/terminal notification, setup excluding Chrome/runtimes, HTTP/IP request keys, CPU guest/iowait math, network reset/gap behavior, MemAvailable, missing observations, bounded history and persistence throttling.

Frontend lint, strict TypeScript and production build passed. Three Node markup/behavior tests passed, including fast terminal responses, refreshed pending work, failure notices, duplicate suppression and suppression of historical success notices. Both CI workflows now run those tests. Installer: 81 passed / zero failed; release assembly/signing contracts: 33; prerequisites/ASCII: 40; security: 5. Operations harness passed.

Python software contracts: 21 passed as root on each Ubuntu 22.04/24.04/26.04 lab, including socket peer denial, asynchronous acknowledgement, interrupted-process reconciliation, partial batch continuation versus unsafe abort, pinned dependency plans, revocation, traversal/link/device rejection, set-id stripping, restrictive umask permissions, timeout descendants, private APT preferences, service failure and mirror/hash verification. Development host intentionally skips the root-only socket test.

Installer fixture matrices passed on all three container images at `installer-network-22.04-20261019T190830Z`, `installer-network-24.04-20261019T190843Z`, and `installer-network-26.04-20261019T190858Z`.

## Actual package tests in disposable containers

Fresh `--init` containers were used for expanded catalog tests, with only database authorization and optional systemd checks substituted. Real APT resolution/download/install, binary execution, archive hash/extraction, Node/npm and temporary MongoDB hello verification ran. These substitutions are explicitly not service/VM certification. Reports are local ignored artifacts under `tests/infrastructure/ubuntu-lab/reports/catalog-expanded-20261019/`.

On 22.04: htop, jq, FFmpeg, Unzip, Python/venv, MySQL, Memcached, ClamAV/Freshclam, Supervisor and Node installed/verified; second runs made no changes. MariaDB was safely refused against MySQL. A separate fresh MariaDB-first run installed/verified MariaDB and refused MySQL, proving both directions without removal. MongoDB subsequently passed binary/engine/idempotency checks with the pinned source/fallback configuration.

On 24.04: htop, jq, FFmpeg, Unzip, Python/venv, MySQL, Memcached, Supervisor, Node and MongoDB installed/verified with unchanged second runs. Initial ClamAV dependency resolution would upgrade existing libraries and was refused. A private per-execution installed-version preference fixed unnecessary upgrades without editing global APT; a fresh follow-up installed/verified ClamAV/Freshclam and FFmpeg, including unchanged second runs.

Protected panel Nginx/PHP/PostgreSQL/Redis were correctly refused as CORE_SERVICE_MISSING in clean images without the panel prerequisites. Their actual panel instances passed the native checks below. Initial catalog tests in old containers without an init process exposed MySQL postinstall zombie-wait behavior and a timeout that did not kill inherited-pipe descendants. Those attempts were not counted as passed. The helper now kills the whole process group on timeout; the real descendant-pipe regression passes, and fresh containers use `--init`.

Chrome archives downloaded and extracted with exact pinned hashes. Minimal Ubuntu lacked Cairo/Pango; these dependencies were added and the actual unprivileged version check passed. Docker's AppArmor/userns isolation refused sandbox activation; no `--no-sandbox` bypass was used and no successful container rendering is claimed. The native attempt below was blocked by dependencies before rendering; VM rendering remains unverified.

Ubuntu 26.04 runs contracts and refuses the production software capability before APT/service mutation. It is not advertised as supported.

## Regional MongoDB availability

Both official MongoDB hostnames returned HTTP 403 from the authorized Iran VPS. Unchanged official vendor archives for 22.04/24.04 were fetched in the disposable lab, verified against official pinned hashes and attached as optional public beta37 runtime assets. GitHub's asset digests match those hashes. A read-only probe from the actual VPS received HTTP 200 from the pinned mirror. The signed catalog's exact fallback URL and checksum are mandatory; checksum mismatch never falls through silently. Bootstrap does not fetch these optional runtime assets.

## Release and VPS results

The following sections record executed signed release verification, controlled activation and native GUI/package/monitoring tests. Local/container results alone do not assert full VM success. Intermediate beta35 was withdrawn before activation after its missing Chrome dependencies were found; beta36 CI was cancelled before publication while the terminal-response Toast fix was added. Neither is a successful VPS installation claim.

### Signed beta38 delivery and activation

Public release `v1.0.0-beta.38.1`, source `e89bccd1ab9f162d24c1c208f6565282d1234477`, CI run `39313305296`: candidate, build and publication passed. All eight public assets downloaded; both signatures, SHA256SUMS, manifest artifact digest, packaged helper bytes and bootstrap `--verify-only` verified. Bootstrap SHA256: `258b97368c22f44875efe120f1c12699c12333bc6aeb80e9e939f7ae1a962758`. Trust-key SHA256: `7e2589449bce2c3bea617494784f22f211a74ec1d419ad232213e165c8160d09`.

Actual Ubuntu 24.04 VPS supervised upgrade beta34 -> beta38 passed signature/checksum/schema preflight, exclusive locks, stopped-writer PostgreSQL custom backup and restore-list inspection, configuration/runtime archive inspection, activation, service-user doctor, backend installed-version, separate unit checks and HTTP welcome/login/up on port 8080. Schema/seeders were byte-identical; no migration or seeder ran. Panel/master/system credentials remained byte-identical. Backups retained root-only at `/root/rahandaz-update-beta38/backup`. Actual database restoration, reboot, automated updater activation and 22.04/26.04 VM certification were not exercised.

### Native GUI checks already executed

Authenticated owner GUI selected jq and confirmed installation through the normal HTTP -> Job/Outbox -> scheduler -> queue -> root socket. Job `01a155b5-a017-720d-8f16-8860a76a9c23` succeeded with jq `1.7.1-3build1`, changed=false. The button immediately became disabled with aria-busy=true and remained loading until terminal success. Actual completion Toast and counts total=1/succeeded=1/failed=0/skipped=0 appeared. No manual status mutation or outbox dispatch was used.

All eighteen choices rendered under seven Persian categories. Catalog/monitoring at mobile widths 320/390 and desktop 1440 had no page-level horizontal overflow; native required attributes count=0. Dark/light rendering and mobile right-side navigation were exercised; original system theme and viewport restored. Node's verified fixed path displayed and the HTTP fallback selected the whole read-only value for manual copy. The prior HTTP/IP deployments white-page error was reproduced before upgrade and the actual deployments page rendered after the backend request-key fix.

Monitoring automatically progressed from a first rate-less observation to 114+ genuine samples, timestamp updates, CPU/network rates and real SVG history. Examples: 549.8 MiB RAM at first observation, later 592 MiB/1.9 GiB; root disk 2.9 -> 3.2 GiB as explicit software tests installed packages. Independent proc delta measured CPU 40.8% over three seconds near a panel interval of 36.18%; these are adjacent intervals, not an exact simultaneous comparison. Independent MemAvailable-based used memory was 599 MiB near the panel 592-600 MiB range. History persisted approximately once/minute, older records were marked stale. Browser offline simulation was not run; automated reset/gap and missing-data cases passed. Service health/alerts were not fabricated from metric freshness.

### Diagnosed native package constraints

APT refusal was reproduced read-only with the exact private execution preferences and signed current source indexes. FFmpeg's new Mesa dependencies require libdrm2 >=2.4.125 but the installed libdrm2 is 2.4.122; Python/venv requires python3 3.12.3-0ubuntu2.1 but the installed version is 3.12.3-0ubuntu2; MySQL's libevent-pthreads requires libevent-core 2.1.12-stable-9ubuntu2.2 but 2.1.12-stable-9ubuntu2 is installed. These are real patch-level dependency requirements, not failures fixed by silently allowing global upgrades. No existing package was removed/upgraded to conceal them. MariaDB was also refused during dependency planning; MySQL had not installed, so this native result is not a MySQL/MariaDB service-conflict test (that conflict passed in separate container tests).

Freshclam was installed as a ClamAV dependency, but its systemd unit exited 17. The actual journal reports daily.cvd HTTP403 from database.clamav.net, blocked CDN and a 24-hour cool-down. Independent Freshclam verification correctly failed PACKAGE_SERVICE_FAILED. No valid current signature database, active protection or file scanning is claimed; repeated CDN retries, unknown signature mirrors and disabling signature checks were not used.

### Native MongoDB download outcome

Job `01a155bb-4586-713b-ad94-e70a6386ea38` reached DOWNLOAD_FAILED after bounded download attempts (about 10.5 minutes). The pinned GitHub mirror returned bytes, but the transfer on this VPS did not finish before the deadline. HTTP200 from a probe is not a completed download or verified installation. No MongoDB executable path or native engine success is claimed. The independent SSH test driver lost its channel; the systemd-owned helper continued, recorded the terminal journal and was naturally reconciled by the worker. The driver later recovered the actual terminal result without rewriting Job state. The successful container MongoDB engine checks remain a separate result. Resumable large-runtime transfers over this slow path remain a limitation; increasing a timeout or bypassing hashes was not used to claim a pass.

### Eighteen native catalog outcomes on beta38

Every choice was actually requested individually through the normal capability. Eleven verified outcomes (including GUI jq) and seven failures; none of the latter are advertised as installed. The following UUIDs retain their real audit/logs:

- jq: succeeded, 1.7.1-3build1, changed=false; `01a155b5-a017-720d-8f16-8860a76a9c23` (owner GUI).
- htop: succeeded, 3.3.0-4build1; `01a155b6-724b-7335-a11e-eff9bf2dbb4f`.
- ffmpeg: failed, PACKAGE_CONFLICT; `01a155b6-c1b6-71e7-b685-b5130279e29a`.
- unzip: succeeded, 6.0-28ubuntu4.1; `01a155b7-07a8-7240-ade2-f2a198b8eb67`.
- nginx: succeeded, 1.24.0-2ubuntu7.18; `01a155b7-4793-73fd-8183-9379cbb9d594`.
- php: succeeded, 8.4.26-1+0~20260925.56+ubuntu24.04~1.gbp80d95d; `01a155b7-971b-730f-81fe-ff4a70356fe1`.
- python: failed, PACKAGE_CONFLICT; `01a155b7-db17-738a-9621-d8d1d6585348`.
- postgresql: succeeded, 16+257build1.1; `01a155b8-189b-72ae-ad43-2b8835c7a6f3`.
- redis: succeeded, 5:7.0.15-1ubuntu0.24.04.5; `01a155b8-5531-723a-bfbe-2b6711fa093f`.
- memcached: succeeded, 1.6.24-1ubuntu0.3; `01a155b8-89ef-73cd-906b-f2d057b533c0`.
- supervisor: succeeded, 4.2.5-1ubuntu0.1; `01a155b9-1396-724b-930e-5c2b4fe7ccb1`.
- mysql: failed, PACKAGE_CONFLICT; `01a155b9-9db8-7242-8897-0bcfbacabdbd`.
- mariadb: failed, PACKAGE_CONFLICT; `01a155b9-c92f-71fb-9056-99e06de3ba5c`.
- clamav: succeeded, 1.5.4+dfsg-0ubuntu0.24.04.1; `01a155b9-eb4f-701b-ab20-34711fd25e03`.
- clamav-freshclam: failed, PACKAGE_SERVICE_FAILED; `01a155ba-77b0-71c5-b4ea-09463dae5990`.
- node: succeeded, 24.22.0; `01a155ba-abcb-71f8-a8b7-6d1b98c56123`.
- mongodb: failed, DOWNLOAD_FAILED; `01a155bb-4586-713b-ad94-e70a6386ea38`.
- google-chrome: failed, PACKAGE_CONFLICT; `01a155c5-c6de-7078-a8b1-0219fa9397b0`.

Chrome's native attempt stopped at PACKAGE_CONFLICT while resolving its libraries, before browser download/render. Some earlier new library installs had succeeded; they were retained and existing packages were not upgraded or removed. No native Chrome PNG/PDF success or copyable verified Chrome path is claimed. The generic conflict text incorrectly said no changes were applied; the follow-up beta39 changes this to explicitly acknowledge that earlier new packages may remain. This was a reporting defect, not a reason to hide the native failure.

Mixed owner-GUI batch `01a155c9-4eaa-7339-9f16-57d17a2c124f`: FFmpeg failed dependency planning, then jq was actually verified successfully. Button aria-busy=true and terminal failure Toast were observed. Counts total=2/succeeded=1/failed=1/skipped=0 matched the package rows. Job detail displayed both selected names, phase events, separate final results and final counts; twelve ordered log records were visible, and polling stopped at terminal status. This confirms safe failure continuation instead of silently skipping the rest or announcing whole-batch success.

### Signed beta39 reporting correction

Beta39 source `495efc8167001b9e7ac8d5910489067e54b70286` changes only the conflict guidance: no existing package is automatically upgraded/removed, but earlier newly installed packages may remain. It does not claim transactional rollback. Focused SoftwareSetup PHP tests: 9 passed/100 assertions; syntax and diff checks passed. CI `39317104537` candidate/build/publish passed. All eight public assets, trust-key identity, both signatures, SHA256SUMS, packaged corrected PHP/helper/catalog bytes and bootstrap `--verify-only` passed. Bootstrap SHA256: `12c80fe420bb39f657484d53abaa42105cf8a24cb5f390458c56b4b36703b180`. Package-helper/catalog logic is unchanged from the eighteen beta38 native tests; failures above are not recast as successful installs.

The supervised beta38 -> beta39 upgrade also passed signature/schema/health preflight, exclusive locks, stopped-writer PostgreSQL/config backups and list validation, activation and backend version, service-user doctor, individual service checks, HTTP8080 and unchanged secrets. No migration ran. Root-only backups are retained at `/root/rahandaz-update-beta39/backup`. As before, actual database restore was not exercised.

Final beta39 GUI batch `01a155ce-4fa8-708b-b4b8-d0aaa09d13db` repeated the unchanged FFmpeg conflict + jq verification to validate the corrected reporting. Immediate aria-busy=true, terminal counts 2/1/1/0, per-package outcomes and actual failure Toast all passed. The corrected text explicitly acknowledged earlier newly installed packages. New audit/logs were retained; earlier beta38 records were not rewritten. A final real screenshot was saved without credentials. Latest native checks found empty dpkg audit, active Nginx/PostgreSQL/dedicated Redis/FPM/queue/scheduler/helper sockets/Memcached/Supervisor, HTTP8080 login/up200, original env/master/canary unchanged, and no MongoDB process/listen27017. Doctor confirms PHP8.4 and general execution disabled; secure_cookie warning is expected on the owner's explicitly selected HTTP/IP access. Freshclam's blocked unit is still a known failure, not hidden as healthy.

Final matrix: Ubuntu22.04 = container contracts/packages/idempotency only, VM not_run; Ubuntu24.04 = container tests plus this native VPS scope with the seven explicit failures above; Ubuntu26.04 = contracts/installer fixtures only, software apply deliberately refused. Reboot, real database restoration and full three-VM certification remain not_run. No stable v1 or production-ready claim is made.
