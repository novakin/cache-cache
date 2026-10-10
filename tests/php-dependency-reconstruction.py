"""Check pinned shipped bytes and staging boundaries without upstream history."""
import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'tools/reconstruct-php-dependencies.py'
spec = importlib.util.spec_from_file_location('dependency_reconstruction', SCRIPT)
reconstruction = importlib.util.module_from_spec(spec)
spec.loader.exec_module(reconstruction)


class DependencyReconstruction(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.manifest = json.loads(reconstruction.MANIFEST.read_text(encoding='utf-8'))
        cls.files = reconstruction.inventory(ROOT / 'noobrocket', cls.manifest['source']['trees'])

    def test_current_bundle_and_recovery_patch(self):
        reconstruction.verify(self.files, self.manifest)
        self.assertIn('inc/Dependencies/ActionScheduler/lib/cron-expression/LICENSE', self.files)
        table = self.files['inc/Dependencies/BerlinDB/Database/Table.php']
        self.assertIn(b"doing_action( 'wp_initialize_site' )", table)

    def test_byte_change_is_rejected(self):
        files = dict(self.files)
        name = next(iter(files))
        files[name] += b'\n'
        with self.assertRaises(ValueError):
            reconstruction.verify(files, self.manifest)

    def test_missing_and_extra_files_are_rejected(self):
        missing = dict(self.files)
        missing.pop(next(iter(missing)))
        extra = dict(self.files, unexpected=b'<?php')
        for files in (missing, extra):
            with self.subTest(files=len(files)), self.assertRaises(ValueError):
                reconstruction.verify(files, self.manifest)

    def test_discovery_order_is_irrelevant(self):
        reversed_files = dict(reversed(tuple(self.files.items())))
        self.assertEqual(reconstruction.tree_hash(self.files), reconstruction.tree_hash(reversed_files))

    def test_verification_needs_no_git_history(self):
        result = subprocess.run(
            [sys.executable, str(SCRIPT), '--verify-shipped', '--source-repository', str(ROOT / 'not-a-repository')],
            capture_output=True, text=True,
        )
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_existing_destination_is_preserved(self):
        with tempfile.TemporaryDirectory(prefix='cache-cache-dependencies-') as directory:
            marker = Path(directory) / 'keep.txt'
            marker.write_bytes(b'owned test input')
            result = subprocess.run(
                [sys.executable, str(SCRIPT), '--destination', directory], capture_output=True, text=True,
            )
            self.assertEqual(result.returncode, 1)
            self.assertIn('Destination must be new', result.stderr)
            self.assertEqual(marker.read_bytes(), b'owned test input')
            self.assertEqual(list(Path(directory).iterdir()), [marker])

    def test_maintained_destination_is_refused(self):
        destination = ROOT / 'noobrocket/.dependency-reconstruction-proof'
        self.assertFalse(destination.exists())
        result = subprocess.run(
            [sys.executable, str(SCRIPT), '--destination', str(destination)], capture_output=True, text=True,
        )
        self.assertEqual(result.returncode, 1)
        self.assertIn('Destination must be new', result.stderr)
        self.assertFalse(destination.exists())


if __name__ == '__main__':
    unittest.main()
