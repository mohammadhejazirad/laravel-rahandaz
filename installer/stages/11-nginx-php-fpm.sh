#!/usr/bin/env bash
# Stable id: 11-nginx-php-fpm
# Preconditions: The dedicated site and pool are rendered. nginx -t must pass before enable.
# Error codes: NGINX_TEST_FAILED
# Retry: a failed stage is retried by --resume. A succeeded stage is skipped.
# Recovery: keep the state directory, fix the reported code, then resume.
# Implementation: stage_11_nginx_php_fpm in installer/lib/engine.sh
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
stage_11_nginx_php_fpm "$@"
