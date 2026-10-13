#!/usr/bin/env bash
# Stable id: 03-system-packages
# Preconditions: Base packages are installed or already confirmed.
# Error codes: APT_FAILED PACKAGES_NOT_INSTALLED
# Retry: a failed stage is retried by --resume. A succeeded stage is skipped.
# Recovery: keep the state directory, fix the reported code, then resume.
# Implementation: stage_03_system_packages in installer/lib/engine.sh
set -euo pipefail
INSTALLER_HOME="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${INSTALLER_HOME}/lib/common.sh"
source "${INSTALLER_HOME}/lib/state.sh"
source "${INSTALLER_HOME}/lib/os.sh"
source "${INSTALLER_HOME}/lib/verify.sh"
source "${INSTALLER_HOME}/lib/download.sh"
source "${INSTALLER_HOME}/lib/mirror.sh"
source "${INSTALLER_HOME}/lib/gate.sh"
source "${INSTALLER_HOME}/lib/render.sh"
source "${INSTALLER_HOME}/lib/runtime.sh"
source "${INSTALLER_HOME}/lib/engine.sh"
stage_03_system_packages "$@"
