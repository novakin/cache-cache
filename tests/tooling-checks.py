"""Representative negative controls for the check runner and archive boundary."""
import argparse
from contextlib import redirect_stderr, redirect_stdout
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import shutil
import subprocess
import sys
from zipfile import ZipFile

ROOT = Path(__file__).resolve().parents[1]


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    result = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(result)
    return result


checks = module('cache_cache_checks', ROOT / 'tools/check.py')
packages = module('cache_cache_packages', ROOT / 'tools/verify-package.py')


class ToolingControls(unittest.TestCase):
    def test_missing_php_is_a_failure(self):
        with self.assertRaisesRegex(checks.CheckFailure, 'executable missing'):
            checks.executable('cache-cache-deliberately-missing-php', 'PHP')

    def test_catalog_does_not_silently_omit_a_fixture(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / 'tests').mkdir()
            (root / 'tests/unregistered.php').write_text('<?php', encoding='utf-8')
            (root / 'tests/catalog.json').write_text('{"fixtures":[]}', encoding='utf-8')
            with self.assertRaisesRegex(checks.CheckFailure, 'catalog differs'):
                checks.catalog(root)

    def test_case_insensitive_workstation_cannot_hide_broken_linux_links(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / 'guide.md').write_text('fixture', encoding='utf-8')
            self.assertTrue(checks.exact_case_exists(root, 'guide.md'))
            self.assertFalse(checks.exact_case_exists(root, 'Guide.md'))

    def test_helpers_are_not_executable_checks(self):
        args = argparse.Namespace(profile='wordpress', fixture=['wordpress-http-audit'])
        with self.assertRaisesRegex(checks.CheckFailure, 'helper'):
            checks.fixtures(ROOT, args)

    def test_deliberately_invalid_php_fails_syntax(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            for name in ('noobrocket', 'tests', 'tools'):
                (root / name).mkdir()
            (root / 'noobrocket/broken.php').write_text('<?php function ( invalid;', encoding='utf-8')
            with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
                with self.assertRaisesRegex(checks.CheckFailure, 'Syntax noobrocket/broken.php'):
                    checks.syntax(root, os.environ.get('NOOBROCKET_TEST_PHP', 'php'), os.environ.get('NOOBROCKET_TEST_NODE', 'node'))

    def test_archive_traversal_is_rejected_before_extraction(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'bad.zip'
            with ZipFile(path, 'w') as archive:
                archive.writestr('noobrocket/../wp-config.php', 'fixture')
            with self.assertRaisesRegex(ValueError, 'Unsafe archive path'):
                packages.verify(path)

    def test_unsupported_fixture_selection_is_a_failure(self):
        with redirect_stdout(io.StringIO()), redirect_stderr(io.StringIO()):
            self.assertEqual(1, checks.main(['docs', '--fixture', 'deliberately-missing']))
            self.assertEqual(1, checks.main(['server', '--fixture', 'linux-production']))

    def test_private_environment_variants_never_enter_a_build(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / 'tools').mkdir()
            (root / 'noobrocket').mkdir()
            shutil.copy2(ROOT / 'tools/build.py', root / 'tools/build.py')
            (root / 'noobrocket/noobrocket.php').write_text('<?php\n * Version: 0.1.0-beta.99\n', encoding='utf-8')
            for name in ('.env', '.env.local'):
                (root / 'noobrocket' / name).write_text('nonsecret fixture', encoding='utf-8')
            archive_path = root / 'fixture.zip'
            result = subprocess.run([sys.executable, str(root / 'tools/build.py'), '--output', str(archive_path)], capture_output=True, text=True)
            self.assertEqual(0, result.returncode, result.stderr)
            with ZipFile(archive_path) as archive:
                self.assertEqual(['noobrocket/noobrocket.php'], archive.namelist())
            with ZipFile(archive_path, 'w') as archive:
                archive.writestr('noobrocket/.env.local', 'nonsecret fixture')
            with self.assertRaisesRegex(ValueError, 'private file'):
                packages.verify(archive_path)

    def test_existing_runtime_directory_is_not_replaced(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            path = root / 'wp-content/mu-plugins/cache-cache-check-http.php'
            path.parent.mkdir(parents=True)
            path.write_text('owned elsewhere', encoding='utf-8')
            with self.assertRaisesRegex(checks.CheckFailure, 'Refusing to replace'):
                with checks.native_http_boundary(root):
                    self.fail('Existing guard should have blocked entry')
            self.assertEqual('owned elsewhere', path.read_text(encoding='utf-8'))

    def test_fixed_http_port_must_serve_this_exact_disposable_site(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with patch.object(checks, 'urlopen', return_value=io.BytesIO(b'wrong site')):
                with self.assertRaisesRegex(checks.CheckFailure, 'does not serve'):
                    checks.owned_http_origin(root)
            self.assertEqual([], list(root.iterdir()))


if __name__ == '__main__':
    unittest.main()
