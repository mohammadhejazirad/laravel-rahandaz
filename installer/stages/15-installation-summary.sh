#!/usr/bin/env bash
# Stable id: 15-installation-summary
# Preconditions: Success is reported only when every essential stage succeeded.
# Error codes: STAGE_FAILED
# Retry: a failed stage is retried by --resume. A succeeded stage is skipped.
# Recovery: keep the state directory, fix the reported code, then resume.
# Implementation: stage_15_installation_summary in installer/lib/engine.sh
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
stage_15_installation_summary "$@"
