#!/usr/bin/env bash

ops_docker_plan() {
    python3 -c 'import json,sys; print(json.dumps({"action":sys.argv[1],"slug":sys.argv[2],"layout":sys.argv[3],"compose_file":sys.argv[4],"dockerfile":sys.argv[5],"service":sys.argv[6]},separators=(",",":")))' "$1" "$2" "$3" "$4" "$5" "$6"
}

ops_docker_optional() {
    local key="$1"
    ops_get "$key" || true
}

ops_docker_action() {
    local action="$1"
    local slug layout compose_file dockerfile service plan root observed
    slug="$(ops_require_text project_slug)"
    ops_check docker-slug "$slug"
    layout="$(ops_require_text layout)"
    case "$layout" in
        compose_only | dockerfile_only | compose_and_dockerfile) ;;
        *) ops_fail INVALID_INPUT "Docker layout is not recognized." ;;
    esac
    compose_file="$(ops_docker_optional compose_file)"
    dockerfile="$(ops_docker_optional dockerfile)"
    service="$(ops_docker_optional service)"
    if [[ -z "$compose_file" ]]; then
        compose_file="compose.yml"
    fi
    if [[ -z "$dockerfile" ]]; then
        dockerfile="Dockerfile"
    fi
    ops_check docker-file compose "$compose_file"
    ops_check docker-file dockerfile "$dockerfile"
    if [[ -n "$service" ]]; then
        ops_check ident "$service"
    fi
    case "$layout" in
        compose_only)
            if [[ "$action" == "build" ]]; then
                ops_fail NO_DOCKERFILE "A Compose-only project has no image build."
            fi
            ;;
        dockerfile_only)
            if [[ "$action" != "build" && "$action" != "status" ]]; then
                ops_fail NO_COMPOSE_FILE "This project has a Dockerfile and no Compose file."
            fi
            ;;
    esac
    plan="$(ops_docker_plan "$action" "$slug" "$layout" "$compose_file" "$dockerfile" "$service")"
    if ! ops_wants_apply; then
        ops_preview "$plan" '{"observed":false,"host_executed":false}'
    fi
    if [[ "$action" != "status" ]]; then
        ops_require_approval
    fi
    ops_require_sandbox_apply
    root="/srv/projects/${slug}"
    if [[ -L "$root" || ! -d "$root" ]]; then
        ops_fail PROJECT_ROOT_MISSING "The project directory is not a real directory under /srv/projects."
    fi
    local names=()
    local base
    while IFS= read -r base; do
        [[ -n "$base" ]] || continue
        names+=("$base")
    done < <(find "$root" -maxdepth 1 -type f -printf '%f\n')
    if [[ ${#names[@]} -eq 0 ]]; then
        observed="$(ops_py docker-layout)"
    else
        observed="$(ops_py docker-layout "${names[@]}")"
    fi
    if [[ "$observed" != "$layout" ]]; then
        ops_fail LAYOUT_MISMATCH "The files in the project root do not match the selected layout."
    fi
    case "$action" in
        status)
            if [[ "$layout" == "dockerfile_only" ]]; then
                ops_exec docker image inspect -- "${slug}:local" >/dev/null
            else
                ops_docker_compose "$root" "$slug" "$compose_file" ps
            fi
            ;;
        up) ops_docker_compose "$root" "$slug" "$compose_file" up -d ${service:+"$service"} ;;
        stop) ops_docker_compose "$root" "$slug" "$compose_file" stop ${service:+"$service"} ;;
        restart) ops_docker_compose "$root" "$slug" "$compose_file" restart ${service:+"$service"} ;;
        down) ops_docker_compose "$root" "$slug" "$compose_file" down --remove-orphans ;;
        build) ops_exec docker build --file "${root}/${dockerfile}" --tag "${slug}:local" -- "$root" ;;
        *) ops_fail INVALID_INPUT "Unknown Docker action." ;;
    esac
    ops_succeed "$plan" '{"observed":true,"host_executed":true}' true
}

ops_docker_compose() {
    local root="$1" slug="$2" compose_file="$3"
    shift 3
    ops_exec docker compose --project-directory "$root" --project-name "$slug" --file "${root}/${compose_file}" "$@"
}
