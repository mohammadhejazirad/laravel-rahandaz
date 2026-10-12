#!/usr/bin/env bash

ops_plan() {
    python3 -c 'import json,sys; print(json.dumps(json.loads(sys.argv[1]), ensure_ascii=False, separators=(",", ":")))' "$1"
}
