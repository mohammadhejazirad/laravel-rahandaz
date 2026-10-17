"""Release version boundaries; no network or host mutation."""

import importlib.util
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("candidate", Path(__file__).resolve().parents[1] / "release/candidate.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class CandidateTest(unittest.TestCase):
    def test_push_and_rerun_have_distinct_prerelease_versions(self):
        self.assertEqual(module.candidate("push", "1.0.0", "", "31", "1"), "v1.0.0-beta.31.1")
        self.assertEqual(module.candidate("push", "1.0.0", "", "31", "2"), "v1.0.0-beta.31.2")

    def test_manual_tag_is_preserved(self):
        for tag in ("v1.0.0-rc.1", "v1.0.0-beta.31.2", "v2.1.0-alpha.0"):
            self.assertEqual(module.candidate("workflow_dispatch", "1.0.0", tag, "1", "1"), tag)

    def test_stable_and_untrusted_tags_are_rejected(self):
        for tag in ("v1.0.0", "v01.0.0-rc.1", "v1.0.0-rc.01", "../../main", "v1.0.0-rc.1\ncommit=bad", "$(id)"):
            with self.subTest(tag=tag), self.assertRaises(ValueError):
                module.candidate("workflow_dispatch", "1.0.0", tag, "1", "1")

    def test_invalid_event_core_and_identifiers_are_rejected(self):
        for values in (("pull_request", "1.0.0", "", "1", "1"),
                       ("push", "1.0.0-rc.1", "", "1", "1"),
                       ("push", "1.0.0", "", "01", "1"),
                       ("push", "1.0.0", "", "1", "0")):
            with self.subTest(values=values), self.assertRaises(ValueError):
                module.candidate(*values)


if __name__ == "__main__":
    unittest.main(verbosity=2)
