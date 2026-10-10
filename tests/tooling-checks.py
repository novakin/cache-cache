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
    def php_http_fixture(self, root, body, audit_first=False):
        """Execute the real PHP guards with a modeled hook dispatcher; never send HTTP."""
        fixture = root / 'http-control.php'
        fixture.write_text('''<?php
class WP_Error {}
function add_filter($hook, $callback, $priority = 10, $args = 1) { if ($hook === 'pre_http_request') { $GLOBALS['http_filters'][$priority][] = $callback; } }
function fixture_http($url) {
    ksort($GLOBALS['http_filters']);
    $pre = false;
    foreach ($GLOBALS['http_filters'] as $callbacks) {
        foreach ($callbacks as $callback) { $pre = $callback($pre, [], $url); }
    }
    return $pre;
}
function wp_parse_url($url) { return parse_url($url); }
function wp_json_encode($value) { return json_encode($value); }
define('WP_CONTENT_DIR', dirname($argv[1], 2));
''' + ("require $argv[2];\n" if audit_first else '') + "require $argv[1];\n" + body, encoding='utf-8')
        php = checks.executable(os.environ.get('NOOBROCKET_TEST_PHP', 'php'), 'PHP')
        return subprocess.run([php, str(fixture), str(root / 'wp-content/mu-plugins/cache-cache-check-http.php'), str(ROOT / 'tests/wordpress-http-audit.php')], capture_output=True, text=True)

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

    def test_ignored_blocked_http_fails_after_fixture_and_guard_cleanup(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with self.assertRaisesRegex(checks.CheckFailure, 'Unexpected HTTP blocked'):
                with checks.native_http_boundary(root):
                    result = self.php_http_fixture(root, "fixture_http('https://unexpected.example.test/private?token=fixture'); echo 'fixture completed';")
                    self.assertEqual(0, result.returncode, result.stderr)
                    self.assertEqual('fixture completed', result.stdout)
            self.assertFalse((root / 'wp-content/mu-plugins/cache-cache-check-http.php').exists())
            self.assertEqual([], list((root / 'wp-content').glob('cache-cache-http-*')))

    def test_synthetic_success_and_error_remain_valid_before_terminal_guard(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with checks.native_http_boundary(root, 'http://127.0.0.1:8765'):
                result = self.php_http_fixture(root, '''
$response = ['response' => ['code' => 200], 'body' => 'fixture'];
$error = new WP_Error();
add_filter('pre_http_request', function ($pre, $args, $url) use ($response, $error) {
    return strpos($url, '/success') !== false ? $response : (strpos($url, '/error') !== false ? $error : $pre);
}, PHP_INT_MAX - 1, 3);
if (fixture_http('https://provider.example.test/success') !== $response || fixture_http('https://provider.example.test/error') !== $error || fixture_http('http://127.0.0.1:8765/owned') !== false) { exit(3); }
''')
                self.assertEqual(0, result.returncode, result.stderr)

    def test_unowned_loopback_and_origin_lookalikes_are_failures(self):
        for url, origin in [('http://127.0.0.1:8765/owned', None), ('http://127.0.0.1:8766/other', 'http://127.0.0.1:8765'), ('https://127.0.0.1:8765/other', 'http://127.0.0.1:8765'), ('http://127.0.0.1:8765.example.test/other', 'http://127.0.0.1:8765')]:
            with self.subTest(url=url), tempfile.TemporaryDirectory() as folder:
                root = Path(folder)
                with self.assertRaises(checks.CheckFailure):
                    with checks.native_http_boundary(root, origin):
                        result = self.php_http_fixture(root, f'fixture_http({json.dumps(url)});')
                        self.assertEqual(0, result.returncode, result.stderr)

    def test_existing_audit_cannot_hide_an_unexpected_request(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with self.assertRaisesRegex(checks.CheckFailure, 'Unexpected HTTP blocked'):
                with checks.native_http_boundary(root):
                    result = self.php_http_fixture(root, "fixture_http('https://unexpected.example.test/private?token=fixture');", audit_first=True)
                    self.assertEqual(0, result.returncode, result.stderr)

    def test_unwritable_blocked_receipt_fails_in_php(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with self.assertRaisesRegex(checks.CheckFailure, 'PHP control failed'):
                with checks.native_http_boundary(root):
                    result = self.php_http_fixture(root, "unlink(NOOBROCKET_CHECK_HTTP_RECEIPT); mkdir(NOOBROCKET_CHECK_HTTP_RECEIPT); fixture_http('https://unexpected.example.test/');")
                    self.assertNotEqual(0, result.returncode)
                    self.assertIn('Cannot record blocked fixture HTTP.', result.stderr + result.stdout)
                    raise checks.CheckFailure('PHP control failed')
            self.assertFalse((root / 'wp-content/mu-plugins/cache-cache-check-http.php').exists())

    def test_audit_server_boundary_records_only_unmocked_unowned_blocks(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with self.assertRaisesRegex(checks.CheckFailure, 'Unexpected HTTP blocked'):
                with checks.native_http_boundary(root):
                    # The shared audit also runs independently in the Linux harness.
                    result = self.php_http_fixture(root, '''
$GLOBALS['http_filters'] = [];
define('NOOBROCKET_CHECK_HTTP_ORIGIN', 'http://127.0.0.1:8875');
require $argv[2];
$response = ['body' => 'mock'];
add_filter('pre_http_request', function ($pre, $args, $url) use ($response) { return strpos($url, '/mock') !== false ? $response : $pre; }, PHP_INT_MAX - 1, 3);
if (fixture_http('https://provider.example.test/mock') !== $response || fixture_http('http://127.0.0.1:8875/owned') !== false || filesize(NOOBROCKET_CHECK_HTTP_RECEIPT) !== 0) { exit(3); }
fixture_http('http://127.0.0.1:8765/unowned?token=fixture');
if (file_get_contents(NOOBROCKET_CHECK_HTTP_RECEIPT) !== "blocked\\n") { exit(4); }
''')
                    self.assertEqual(0, result.returncode, result.stderr)

    def test_fixed_http_port_must_serve_this_exact_disposable_site(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            with patch.object(checks, 'urlopen', return_value=io.BytesIO(b'wrong site')):
                with self.assertRaisesRegex(checks.CheckFailure, 'does not serve'):
                    checks.owned_http_origin(root)
            self.assertEqual([], list(root.iterdir()))

    def test_owned_origin_probe_accepts_only_this_sites_token_and_cleans_up(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            def own_response(url, timeout):
                return io.BytesIO(next(root.glob('cache-cache-http-proof-*.txt')).read_bytes())
            with patch.object(checks, 'urlopen', side_effect=own_response):
                self.assertEqual('http://127.0.0.1:8765', checks.owned_http_origin(root))
            self.assertEqual([], list(root.iterdir()))


if __name__ == '__main__':
    unittest.main()
