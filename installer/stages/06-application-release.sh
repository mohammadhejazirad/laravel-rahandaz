#!/usr/bin/env bash
# Stable id: 06-application-release
# Preconditions: The verified artifact is extracted and current points at it.
# Error codes: UNVERIFIED_ARTIFACT ARTIFACT_INCOMPLETE
# Retry: a failed stage is retried by --resume. A succeeded stage is skipped.
# Recovery: keep the state directory, fix the reported code, then resume.
# Implementation: stage_06_application_release in installer/lib/engine.sh
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
stage_06_application_release "$@"
