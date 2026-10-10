"""Execute real quality tools against seeded defects and maintained owners in an owned copy."""
import importlib.util
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('cache_cache_quality', ROOT / 'tools/check.py')
checks = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checks)


def expect_failure(root, php, path, label, evidence):
    previous = set((root / '.artifacts/check-logs').glob('*.log'))
    try:
        checks.quality(root, php, files=[path])
    except checks.CheckFailure as error:
        assert label in str(error), str(error)
        logs = set((root / '.artifacts/check-logs').glob('*.log')) - previous
        assert any(evidence in log.read_text(encoding='utf-8') for log in logs), f'Missing expected diagnostic: {evidence}'
    else:
        raise AssertionError(f'Seeded {label} defect incorrectly passed')


def git(root, *args):
    return subprocess.check_output(['git', *args], cwd=root, text=True, stderr=subprocess.STDOUT).strip()


def scope_controls(root):
    """Exercise actual Git revisions, staged/untracked changes and rename selection."""
    git(root, 'init', '--quiet')
    git(root, 'config', 'user.email', 'fixture@example.invalid')
    git(root, 'config', 'user.name', 'Disposable quality fixture')
    old = root / 'noobrocket/old.php'
    old.write_text('<?php\n', encoding='utf-8')
    git(root, 'add', 'noobrocket/old.php')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks', 'commit', '--quiet', '-m', 'fixture base')
    base = git(root, 'rev-parse', 'HEAD')
    git(root, 'mv', 'noobrocket/old.php', 'noobrocket/renamed file.php')
    for name in ('noobrocket/inc/new.php', 'noobrocket/inc/deprecated/touched.php',
                 'noobrocket/inc/Dependencies/excluded.php', 'noobrocket/vendor/excluded.php',
                 'noobrocket/inc/classes/dependencies/excluded.php',
                 'noobrocket/inc/deprecated/vendors/excluded.php', 'noobrocket/inc/vendors/excluded.php',
                 'docs/new.md'):
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('<?php\n' if name.endswith('.php') else 'fixture\n', encoding='utf-8')
    expected = ['noobrocket/inc/deprecated/touched.php', 'noobrocket/inc/new.php', 'noobrocket/renamed file.php']
    assert checks.quality_paths(root) == expected
    git(root, 'add', '.')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks', 'commit', '--quiet', '-m', 'fixture changed scope')
    assert checks.quality_paths(root, base, 'HEAD') == expected
    head = git(root, 'rev-parse', 'HEAD')
    git(root, 'checkout', '--quiet', '--detach', base)
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks',
        'merge', '--no-ff', '--quiet', '-m', 'fixture integration candidate', head)
    with tempfile.TemporaryDirectory(prefix='cache-cache-quality-shallow-') as folder:
        shallow = Path(folder) / 'checkout'
        git(root, 'clone', '--quiet', '--depth=1', root.as_uri(), str(shallow))
        try:
            checks.quality_paths(shallow, base, head)
        except checks.CheckFailure as error:
            assert 'Git scope failed' in str(error), str(error)
        else:
            raise AssertionError('Shallow merge checkout unexpectedly retained parent revision scope')
        git(shallow, 'fetch', '--quiet', '--unshallow', 'origin', git(root, 'rev-parse', 'HEAD'))
        assert checks.quality_paths(shallow, base, head) == expected
    (root / 'noobrocket/renamed file.php').unlink()
    assert checks.quality_paths(root) == []
    for files in (['../outside.php'], [str(root / 'noobrocket/inc/new.php')],
                  ['noobrocket/vendor/excluded.php'], ['docs/new.md'], ['noobrocket/missing.php']):
        try:
            checks.quality_paths(root, files=files)
        except checks.CheckFailure:
            pass
        else:
            raise AssertionError(f'Invalid explicit quality scope accepted: {files}')


def prerequisite_control(root, php):
    path = root / 'noobrocket/probe.php'
    path.parent.mkdir(parents=True)
    path.write_text('<?php\n', encoding='utf-8')
    try:
        checks.quality(root, php, files=['noobrocket/probe.php'])
    except checks.CheckFailure as error:
        assert 'Quality tools missing' in str(error), str(error)
    else:
        raise AssertionError('Missing tools incorrectly passed a nonempty scope')


def divergence_control(root):
    """The PR integration scope must respect changes already merged into its base."""
    git(root, 'init', '--quiet')
    git(root, 'config', 'user.email', 'fixture@example.invalid')
    git(root, 'config', 'user.name', 'Disposable quality fixture')
    old = root / 'noobrocket/old.php'
    old.parent.mkdir()
    old.write_text('<?php\n', encoding='utf-8')
    git(root, 'add', '.')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks', 'commit', '--quiet', '-m', 'fixture shared base')
    shared = git(root, 'rev-parse', 'HEAD')
    git(root, 'checkout', '--quiet', '-b', 'topic')
    (root / 'noobrocket/new.php').write_text('<?php\nfunction noobrocket_quality_new() {}\n', encoding='utf-8')
    git(root, 'add', '.')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks', 'commit', '--quiet', '-m', 'fixture topic PHP')
    head = git(root, 'rev-parse', 'HEAD')
    git(root, 'checkout', '--quiet', '--detach', shared)
    git(root, 'rm', '--quiet', 'noobrocket/old.php')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks', 'commit', '--quiet', '-m', 'fixture base deletion')
    base = git(root, 'rev-parse', 'HEAD')
    git(root, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=.git/hooks',
        'merge', '--no-ff', '--quiet', '-m', 'fixture integrated topic', head)
    try:
        checks.quality_paths(root, base, head)
    except checks.CheckFailure as error:
        assert 'selected file missing' in str(error), str(error)
    else:
        raise AssertionError('Direct base/head diff unexpectedly respected base-side deletion')
    assert checks.quality_paths(root, base, 'HEAD') == ['noobrocket/new.php']


def main():
    php = checks.executable(os.environ.get('NOOBROCKET_TEST_PHP', 'php'), 'PHP')
    php_version = int(subprocess.check_output([php, '-r', 'echo PHP_VERSION_ID;'], text=True))
    if not (ROOT / 'tools/php-quality/vendor/bin/phpstan').is_file():
        raise checks.CheckFailure('Install isolated tools/php-quality dependencies before this fixture')
    with tempfile.TemporaryDirectory(prefix='cache-cache-quality-scope-') as folder:
        root = Path(folder)
        (root / 'noobrocket').mkdir()
        scope_controls(root)
    with tempfile.TemporaryDirectory(prefix='cache-cache-quality-prerequisite-') as folder:
        prerequisite_control(Path(folder), php)
    with tempfile.TemporaryDirectory(prefix='cache-cache-quality-divergence-') as folder:
        divergence_control(Path(folder))
    with tempfile.TemporaryDirectory(prefix='cache-cache-quality-tools-') as folder:
        root = Path(folder)
        shutil.copytree(ROOT / 'noobrocket', root / 'noobrocket', ignore=shutil.ignore_patterns('node_modules'))
        shutil.copytree(ROOT / 'tools/php-quality', root / 'tools/php-quality')
        probe = 'noobrocket/inc/quality-probe.php'
        path = root / probe
        path.write_text('<?php\nfunction noobrocket_quality_probe( string $value ): string {\n\techo $value;\n\treturn $value;\n}\n', encoding='utf-8')
        expect_failure(root, php, probe, 'quality-phpcs', 'WordPress.Security.EscapeOutput.OutputNotEscaped')
        path.write_text('<?php\nfunction noobrocket_quality_probe( string $value ): string {\n\treturn noobrocket_deliberately_missing_quality_function( $value );\n}\n', encoding='utf-8')
        expect_failure(root, php, probe, 'quality-phpstan', 'not found')
        path.write_text('<?php\nfunction noobrocket_quality_probe( string $value ): string {\n\treturn sanitize_text_field( $value );\n}\n', encoding='utf-8')
        checks.quality(root, php, files=[probe])
        valid = path.read_text(encoding='utf-8')
        path.write_text('<?php\nfunction noobrocket_quality_probe( $value ) { return $value?->unknown(); }\n', encoding='utf-8')
        if php_version < 80000:
            expect_failure(root, php, probe, 'quality-syntax', 'syntax error')
        else:
            checks.quality(root, php, files=[probe])
            print('Known compatibility limit: stable static tools accept nullsafe syntax; CI PHP7.4 native parsing owns the syntax floor')
        path.write_text(valid, encoding='utf-8')
        owners = [
            'noobrocket/inc/Engine/Common/Cache/FilesystemCache.php',
            'noobrocket/inc/Engine/Media/Lazyload/CSS/Subscriber.php',
            'noobrocket/inc/Engine/Preload/Database/Queries/Cache.php',
            'noobrocket/inc/Engine/Optimization/LocalCSS/Frontend.php',
            'noobrocket/inc/Engine/Optimization/RegexTrait.php',
        ]
        checks.quality(root, php, files=owners)
    print('PASS quality tools: real Git scope/shallow recovery/base divergence, escaping/undefined-call defects, native nullsafe syntax control, repaired probe and five maintained owners; source untouched')


if __name__ == '__main__':
    main()
