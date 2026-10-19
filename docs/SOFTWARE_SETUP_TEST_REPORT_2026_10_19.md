# Setup/software capability verification — 2026-10-19

Implemented: durable owner-only initial setup, optional fixed software selection, review, Job/Outbox, restricted root socket installation and verified completion. Tools: htop, jq, FFmpeg, Unzip. No new database migration, frontend dependency, runtime project change or default execution-mode change.

Executed locally:

- PHP: 213 tests / 4,134 assertions passed, PostgreSQL disposable `rahandaz_test`, loopback isolated Redis. Includes setup resume, no HTTP command execution, optional skip, failed health blocking completion, retry, idempotency, fresh membership/ownership, concurrency and false/cross-execution evidence rejection.
- Frontend: lint without warnings, TypeScript strict and production build passed.
- Installer release suite: 33 passed; prerequisites/ASCII suite: 40 passed; installer suite: 81 passed, 0 failed.
- Python helper contract suite: 13 tests on each Ubuntu lab, including a real Unix socket peer denial, asynchronous acknowledgement, killed-process reconciliation, request binding, pinned dependency plan, explicit authorization proof/identity failures, individual unit readiness, authorization revocation and existing-package verification. On the non-root development host, the root-state/socket test is intentionally skipped.
- Ubuntu 22.04 lab: htop 3.0.5-7build2 and jq 1.6-2.1ubuntu3.2 installed; existing FFmpeg 7:4.4.2-0ubuntu0.22.04.1 and Unzip 6.0-26ubuntu3.2 verified. Second run: all unchanged and verified.
- Ubuntu 24.04 lab: htop 3.3.0-4build1 and jq 1.7.1-3ubuntu0.24.04.2 installed; existing FFmpeg 7:6.1.1-3ubuntu5 and Unzip 6.0-28ubuntu4.1 verified. Second run: all unchanged and verified.
- Ubuntu 26.04 lab: contract/socket tests passed; real capability rejects OS_NOT_AVAILABLE before touching services/APT.

The lab script is `tests/infrastructure/ubuntu-lab/scripts/inside/software-capability.py`. It explicitly substitutes DB authorization only within disposable containers to isolate real APT behavior. It is not a systemd, service-account or full VM certificate. FFmpeg/Unzip were already installed, so this run verifies observation/idempotency rather than their fresh installation path. No Ubuntu 22.04/26.04 VM certification or stable v1.0.0 claim.

Installer contracts also passed on all three Ubuntu container images (`installer-network-<version>-20261019T174521Z/`, 81 installer + 33 release tests on each). No full systemd activation was certified by these fixtures.

The live Ubuntu 24.04 results below are separate from container coverage; local/container success alone does not prove the live panel.

## Signed upgrade recovery finding

The first supervised beta23 → beta29 attempt verified signatures, schema identity, database/archive backup, HTTP and doctor, then correctly failed at software-helper enrollment because the release assembler allowed only the older panel-helper directory. No migration ran. The updater restored the beta23 symlink, release metadata, operator code and active panel services. Runtime secrets remained unchanged. This exercised code rollback, not database restore.

The assembler now includes the software directory and requires authorize.php, catalog.json, client.py, helper.py and provision.py before signing. A new regression removes each file in turn and expects assembly failure; completed-bundle tests compare their actual bytes. Installer and updater fixtures were updated and all three container matrices rerun. The incomplete beta27/beta29 releases were withdrawn to private drafts; beta28 was already a private draft after an upload error. The replacement release and its actual live outcomes are recorded below.

The intermediate beta31/beta33 releases passed public signature/archive verification and supervised HTTP/doctor upgrade checks, but their real GUI setup requests failed before package mutation: beta31 hit PAM identity reduction; beta33 exposed missing effective CAP_SETUID in its hardened systemd instance. Neither is a successful live setup test. Their failed jobs/audit remain retained.

The second issue was reproduced on Ubuntu 24.04 with otherwise identical transient service properties: explicit `User=root` plus NoNewPrivileges failed direct UID/GID reduction; the default system-service root identity passed. The final unit retains NoNewPrivileges and its other hardening, omits redundant User=root, and the helper still requires effective UID 0. Direct service UID/GID reduction clears supplementary groups. Authorization requires both exit status zero and exact positive JSON proof; subprocess failures fail closed as AUTHORIZATION_CHECK_FAILED. Read-only readiness passed under the final property set.

## Final signed candidate

Public release v1.0.0-beta.34.1, commit f8975b872f812ae57623f4199d73a543bf683c9d, Actions 39296698171: candidate/build/publish passed. Independently downloaded all eight public assets anonymously, matched the pinned public key, verified both signatures, checked SHA256SUMS and ran `install.sh --verify-only`. The five packaged software runtime files match the tested repository bytes. Bootstrap SHA-256: `78521dda23d2698a1935f3ae8fccf51a627a6891804d341e26dfb3808660e98c`. README quick-install command exactly matches this release's signed guide.

Supervised beta33 -> beta34 upgrade on the real Ubuntu 24.04 VPS passed signatures, identical schema/seeder checks (no migrations), custom PostgreSQL backup/list validation, configuration/runtime archive/list validation, backend installed-version check, public-port HTTP and service-user doctor. Existing secrets stayed byte-identical. Each required panel unit is active. Database restore itself was not tested.

Root socket negative tests on the final signed service: www-data with the socket's group was refused PEER_REFUSED; exact service UID with an unknown execution UUID was refused AUTHORIZATION_REVOKED. No execution journal was created for these requests. Valid GUI authorization/completion results follow below.

## Live GUI onboarding

On the existing Ubuntu 24.04 VPS, the owner selected jq through the GUI; save/reload preserved answers, review showed the selection, and explicit confirmation recorded job `01a15548-08ef-72c3-9354-36d7b762206d`. Natural scheduler/outbox/Redis/worker processing (no manual dispatch, status edit or setup timestamp override) invoked the actual systemd socket helper. Root verified the control plane and existing jq 1.7.1-3build1 with changed=false. The worker accepted fresh execution-bound evidence, wrote requested/started/verified audit events and completed setup at 2026-10-19T17:51:04Z. The page visibly showed success and its real finish link opened the dashboard. General execution remained disabled.

Responsive browser checks passed at widths 320 and 390, with no horizontal overflow or native HTML required fields. The 320px drawer's right edge equaled the viewport right edge. The temporary viewport override was reset. A real completed-setup screenshot was saved locally without credential contents; no VPS screenshot was published in the repository.

## Live GUI software catalog

The same authenticated owner session submitted a real FFmpeg request after reviewing it: job `01a1554a-cfcd-71a2-bf59-3c5e76cdfa2e`. Root APT simulation found existing libdrm/libssh upgrades and returned PACKAGE_CONFLICT before download/apply. Natural reconciliation marked the job failed with requested/started/failed audit. The GUI displayed the specific conflict message and enabled a new selection. No package lock, dependency or source was altered to bypass the conflict.

A subsequent explicitly reviewed catalog request selected htop, jq and Unzip: job `01a1554d-5600-70a7-99c4-de1f4c9a81f7`. Actual systemd helper verification returned htop 3.3.0-4build1, jq 1.7.1-3build1 and Unzip 6.0-28ubuntu4.1, all verified=true and changed=false. Natural queue reconciliation completed successfully and the GUI showed each version as already installed/verified. This proves real catalog dispatch, authorization, safety failure, subsequent request and observation/idempotency on the VPS; it does **not** prove a fresh APT install from the GUI on that VPS. Fresh htop/jq APT coverage is from the disposable labs above; fresh FFmpeg/Unzip VM coverage remains not_run.

A full dpkg package/version/status digest after the FFmpeg rejection matched the pre-request digest, dpkg audit was empty, and all seven required panel/Redis/helper units were individually active. Catalog at 320px had no document overflow or native required inputs; browser warnings/errors were empty. The viewport was restored and the real successful catalog tab kept open. A local screenshot records the visible versions. Failed historical requests and their audit were retained; no failure history was erased.

Remaining limits: general deployment/runtime/database executors and Chrome GUI rendering/install are unfinished; exact dependency-size/plan preview before a second GUI approval is not implemented; APT failure may leave earlier new dependencies installed and has no package-removal rollback. Three-VM systemd/reboot/database-restore certification and Production readiness are not claimed.
