# Setup/software capability verification — 2026-10-19

Implemented: durable owner-only initial setup, optional fixed software selection, review, Job/Outbox, restricted root socket installation and verified completion. Tools: htop, jq, FFmpeg, Unzip. No new database migration, frontend dependency, runtime project change or default execution-mode change.

Executed locally:

- PHP: 213 tests / 4,134 assertions passed, PostgreSQL disposable `rahandaz_test`, loopback isolated Redis. Includes setup resume, no HTTP command execution, optional skip, failed health blocking completion, retry, idempotency, fresh membership/ownership, concurrency and false/cross-execution evidence rejection.
- Frontend: lint without warnings, TypeScript strict and production build passed.
- Installer release suite: 32 passed; prerequisites/ASCII suite: 40 passed; installer suite: 81 passed, 0 failed.
- Python helper contract suite: 10 tests on each Ubuntu lab, including a real Unix socket peer denial, asynchronous acknowledgement, killed-process reconciliation, request binding, pinned dependency plan, authorization revocation and existing-package verification. On the non-root development host, the root-state/socket test is intentionally skipped.
- Ubuntu 22.04 lab: htop 3.0.5-7build2 and jq 1.6-2.1ubuntu3.2 installed; existing FFmpeg 7:4.4.2-0ubuntu0.22.04.1 and Unzip 6.0-26ubuntu3.2 verified. Second run: all unchanged and verified.
- Ubuntu 24.04 lab: htop 3.3.0-4build1 and jq 1.7.1-3ubuntu0.24.04.2 installed; existing FFmpeg 7:6.1.1-3ubuntu5 and Unzip 6.0-28ubuntu4.1 verified. Second run: all unchanged and verified.
- Ubuntu 26.04 lab: contract/socket tests passed; real capability rejects OS_NOT_AVAILABLE before touching services/APT.

The lab script is `tests/infrastructure/ubuntu-lab/scripts/inside/software-capability.py`. It explicitly substitutes DB authorization only within disposable containers to isolate real APT behavior. It is not a systemd, service-account or full VM certificate. FFmpeg/Unzip were already installed, so this run verifies observation/idempotency rather than their fresh installation path. No Ubuntu 22.04/26.04 VM certification or stable v1.0.0 claim.

Live Ubuntu 24.04 signed-release verification is pending publication/deployment at the time of this entry. Update this report with the actual outcome; do not treat local or container success as proof of the live panel.
