"""Select immutable test versions for trusted main-branch release builds."""

import os
from pathlib import Path
import re


NUMBER = r"(?:0|[1-9][0-9]*)"
CORE = rf"{NUMBER}\.{NUMBER}\.{NUMBER}"
CANDIDATE = rf"v{CORE}-(?:alpha|beta|rc)\.{NUMBER}(?:\.{NUMBER})?"


def candidate(event: str, version: str, tag: str, run: str, attempt: str) -> str:
    if event == "workflow_dispatch":
        if not re.fullmatch(CANDIDATE, tag):
            raise ValueError("Only versioned alpha/beta/rc candidate tags are allowed")
        return tag
    if event != "push":
        raise ValueError("Only main pushes and manual candidate builds are allowed")
    if not re.fullmatch(CORE, version):
        raise ValueError("installer/VERSION must contain a stable SemVer core")
    if not all(re.fullmatch(r"[1-9][0-9]*", value) for value in (run, attempt)):
        raise ValueError("Run number and attempt must be positive integers")
    return f"v{version}-beta.{run}.{attempt}"


if __name__ == "__main__":
    version = (Path(__file__).resolve().parents[1] / "VERSION").read_text().strip()
    print("tag=" + candidate(os.environ["CANDIDATE_EVENT"], version,
                             os.environ.get("CANDIDATE_TAG", ""),
                             os.environ["GITHUB_RUN_NUMBER"], os.environ["GITHUB_RUN_ATTEMPT"]))
