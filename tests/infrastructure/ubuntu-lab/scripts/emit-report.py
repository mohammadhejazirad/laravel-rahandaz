#!/usr/bin/env python3
"""Merge host image metadata into a lab JSON report and write JUnit and Markdown."""

from __future__ import annotations

import argparse
import json
import xml.etree.ElementTree as ET
from pathlib import Path


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output-dir", required=True)
    parser.add_argument("--digest", default="")
    parser.add_argument("--architecture", default="")
    args = parser.parse_args()
    report = json.loads(Path(args.input).read_text(encoding="utf-8"))
    if args.digest:
        report["image_digest"] = args.digest
    if args.architecture and not report.get("architecture"):
        report["architecture"] = args.architecture
    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    (out / "result.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    cases = report.get("cases") or []
    suite = ET.Element(
        "testsuite",
        {
            "name": f"ubuntu-{report.get('ubuntu_version', 'unknown')}",
            "tests": str(len(cases)),
            "failures": str(sum(case.get("status") == "failed" for case in cases)),
            "skipped": str(sum(case.get("status") in {"skipped", "unsupported", "requires_vm", "blocked"} for case in cases)),
        },
    )
    for case in cases:
        item = ET.SubElement(suite, "testcase", {"classname": str(case.get("suite")), "name": str(case.get("name")), "time": str(case.get("duration_seconds") or 0)})
        status = case.get("status")
        if status == "failed":
            failure = ET.SubElement(item, "failure", {"message": str(case.get("error_code") or "failed")})
            failure.text = str(case.get("detail") or "")
        elif status != "passed":
            skipped = ET.SubElement(item, "skipped", {"message": str(status)})
            skipped.text = str(case.get("detail") or "")
    ET.ElementTree(suite).write(out / "junit.xml", encoding="utf-8", xml_declaration=True)
    counts = report.get("counts") or {}
    lines = [
        f"# Ubuntu {report.get('ubuntu_version')} lab",
        "",
        f"- Image: `{report.get('image')}`",
        f"- Digest: `{report.get('image_digest')}`",
        f"- Architecture: `{report.get('architecture')}`",
        f"- Environment: `{report.get('environment_type')}`",
        f"- Started: `{report.get('started_at')}`",
        f"- Duration seconds: `{report.get('duration_seconds')}`",
        "",
        "| Status | Count |",
        "| --- | --- |",
    ]
    for key in ("passed", "failed", "skipped", "unsupported", "requires_vm", "blocked"):
        lines.append(f"| {key} | {counts.get(key, 0)} |")
    lines.extend(["", "| Suite | Case | Status | Error | Duration |", "| --- | --- | --- | --- | --- |"])
    for case in cases:
        lines.append(
            f"| {case.get('suite')} | {case.get('name')} | {case.get('status')} | {case.get('error_code') or ''} | {case.get('duration_seconds')} |"
        )
    (out / "summary.md").write_text("\n".join(lines) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
