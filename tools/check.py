"""Run existing Cache-Cache checks. Missing prerequisites are failures, never passes."""
import argparse
from contextlib import contextmanager
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
from urllib.request import urlopen
from urllib.parse import unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
PROFILES = ('docs', 'syntax', 'fast', 'generated', 'wordpress', 'browser', 'server', 'package')


class CheckFailure(Exception):
    pass


def executable(value, label):
    resolved = shutil.which(value) if value else None
    if not resolved and value and Path(value).is_file():
        resolved = str(Path(value).resolve())
    if not resolved:
        raise CheckFailure(f'{label} executable missing: {value!r}')
    return resolved


def run(command, root, label, env=None, timeout=300):
    logs = root / '.artifacts/check-logs'
    logs.mkdir(parents=True, exist_ok=True)
    safe = re.sub(r'[^a-zA-Z0-9_.-]', '-', label)
    stamp = datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%S%fZ')
    log = logs / f'{stamp}-{safe}.log'
    with log.open('w', encoding='utf-8') as output:
        try:
            result = subprocess.run(command, cwd=root, env=env, stdout=output, stderr=subprocess.STDOUT, timeout=timeout, text=True)
        except subprocess.TimeoutExpired as error:
            raise CheckFailure(f'{label}: timed out after {timeout}s; log {log.relative_to(root)}') from error
    if result.returncode:
        lines = log.read_text(encoding='utf-8', errors='replace').splitlines()
        print('\n'.join(lines[-40:]), file=sys.stderr)
        raise CheckFailure(f'{label}: exit {result.returncode}; log {log.relative_to(root)}')
    print(f'PASS {label}; log {log.relative_to(root)}')


def catalog(root):
    path = root / 'tests/catalog.json'
    entries = json.loads(path.read_text(encoding='utf-8'))['fixtures']
    listed = {entry['path'] for entry in entries}
    actual = {path.relative_to(root).as_posix() for path in (root / 'tests').iterdir() if path.suffix in {'.php', '.mjs', '.py'}}
    if listed != actual:
        raise CheckFailure(f'Fixture catalog differs; missing={sorted(actual-listed)}, stale={sorted(listed-actual)}')
    names = [entry['name'] for entry in entries]
    if len(names) != len(set(names)):
        raise CheckFailure('Duplicate fixture names')
    for entry in entries:
        if not all(key in entry for key in ('purpose', 'prerequisites', 'mutation', 'environment', 'profile', 'args', 'default')):
            raise CheckFailure(f'Incomplete fixture entry: {entry["name"]}')
    return entries


def exact_case_exists(base, relative):
    """Windows must catch spelling that a case-sensitive clean Linux checkout rejects."""
    current = base
    for part in Path(relative).parts:
        if part == '..':
            current = current.parent
        elif part == '.':
            continue
        else:
            if not current.is_dir() or part not in {entry.name for entry in current.iterdir()}:
                return False
            current = current / part
    return current.exists()


def docs(root):
    entries = catalog(root)
    paths = [root / name for name in ('README.md', 'AGENTS.md', 'CONTRIBUTING.md', 'tests/README.md', 'tools/DESIGN_GUIDELINES.md')]
    paths.extend(sorted((root / 'docs').glob('*.md')))
    for path in paths:
        if not path.is_file():
            raise CheckFailure(f'Current guide missing: {path.relative_to(root)}')
        text = path.read_text(encoding='utf-8-sig')
        # Historical records retain original workstation evidence; current guides use portable links.
        for match in re.finditer(r'\[[^\]]*\]\((<[^>]*>|[^\s)]*)(?:\s+"[^"]*")?\)', text):
            target = match[1].strip('<>')
            if not target or target.startswith('#') or urlsplit(target).scheme:
                continue
            relative = unquote(target.split('#', 1)[0])
            if not exact_case_exists(path.parent, relative):
                raise CheckFailure(f'Broken local link: {path.relative_to(root)} -> {target}')
    for path in [root / 'noobrocket/composer.json', root / 'tools/package.json', root / 'tools/package-lock.json', root / 'noobrocket/local-css/package.json', root / 'noobrocket/local-css/package-lock.json']:
        json.loads(path.read_text(encoding='utf-8-sig'))
    scripts = json.loads((root / 'noobrocket/composer.json').read_text(encoding='utf-8'))['scripts']
    if any(name.startswith('post-') for name in scripts):
        raise CheckFailure('Composer mutation hooks must not run against bundled dependencies')
    print(f'PASS current docs/config; {len(paths)} guides; {len(entries)} catalog entries')


def syntax(root, php, node, shell='sh'):
    php = executable(php, 'PHP')
    node = executable(node, 'Node')
    files = []
    for folder in ('noobrocket', 'tests', 'tools', '.github/scripts'):
        files.extend(path for path in (root / folder).rglob('*') if path.is_file() and 'node_modules' not in path.parts)
    commands = []
    for path in files:
        relative = path.relative_to(root).as_posix()
        if path.suffix == '.php':
            commands.append(([php, '-l', str(path)], relative))
        elif path.suffix in {'.js', '.mjs', '.cjs'}:
            commands.append(([node, '--check', str(path)], relative))
        elif path.suffix == '.sh':
            commands.append(([executable(shell, 'POSIX shell'), '-n', str(path)], relative))
        elif path.suffix == '.py':
            try:
                compile(path.read_bytes(), relative, 'exec')
            except SyntaxError as error:
                raise CheckFailure(f'Python syntax: {relative}: {error}') from error
    # One log keeps thousands of successful parser lines out of the command output.
    logs = root / '.artifacts/check-logs'
    logs.mkdir(parents=True, exist_ok=True)
    log = logs / f'{datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%S%fZ")}-syntax.log'
    with log.open('w', encoding='utf-8') as output:
        for command, relative in commands:
            output.write(f'\n{relative}\n')
            output.flush()
            result = subprocess.run(command, cwd=root, stdout=output, stderr=subprocess.STDOUT, timeout=60)
            if result.returncode:
                output.flush()
                print('\n'.join(log.read_text(encoding='utf-8', errors='replace').splitlines()[-40:]), file=sys.stderr)
                raise CheckFailure(f'Syntax {relative}: exit {result.returncode}; log {log.relative_to(root)}')
    print(f'PASS syntax; {sum(p.suffix == ".php" for p in files)} PHP, {sum(p.suffix in {".js", ".mjs", ".cjs"} for p in files)} Node, {sum(p.suffix == ".py" for p in files)} Python, {sum(p.suffix == ".sh" for p in files)} shell; log {log.relative_to(root)}')


def generated(root, node):
    node = executable(node, 'Node')
    try:
        import polib  # noqa: F401
    except ImportError as error:
        raise CheckFailure('Generated checks need: python -m pip install -r tools/requirements.txt') from error
    if not (root / 'tools/node_modules/terser').is_dir():
        raise CheckFailure('Generated checks need: npm ci --prefix tools')
    outputs = [root / 'noobrocket/assets/js/noobrocket-settings.min.js']
    outputs.extend(sorted((root / 'noobrocket/languages').glob('*')))
    outputs = [path for path in outputs if path.suffix in {'.js', '.po', '.mo', '.pot'}]
    if not any(path.suffix == '.pot' for path in outputs) or not any(path.suffix == '.po' for path in outputs):
        raise CheckFailure('Catalog inputs missing')
    for path in outputs:
        if path.suffix == '.po' and not path.with_suffix('.mo').is_file():
            raise CheckFailure(f'Compiled catalog missing: {path.with_suffix(".mo").relative_to(root)}')
    before = {path.relative_to(root): hashlib.sha256(path.read_bytes()).digest() for path in outputs}
    with tempfile.TemporaryDirectory(prefix='cache-cache-generated-') as folder:
        copy = Path(folder)
        shutil.copytree(root / 'noobrocket', copy / 'noobrocket', ignore=shutil.ignore_patterns('node_modules'))
        (copy / 'tools').mkdir()
        for name in ('generate-settings-assets.mjs', 'update-translations.py'):
            shutil.copy2(root / 'tools' / name, copy / 'tools' / name)
        # Node resolves deps relative to the copied script; a read-only dependency location stays owned by the source checkout.
        env = dict(os.environ)
        env['NOOBROCKET_TOOLS_DEPENDENCIES'] = str(root / 'tools/package.json')
        run([node, str(copy / 'tools/generate-settings-assets.mjs')], root, 'generated-settings', env)
        run([sys.executable, str(copy / 'tools/update-translations.py')], root, 'generated-translations')
        differences = [str(path) for path, digest in before.items() if not (copy / path).is_file() or hashlib.sha256((copy / path).read_bytes()).digest() != digest]
        if differences:
            raise CheckFailure(f'Generated files stale: {", ".join(differences[:8])}; run documented generators intentionally')
    print(f'PASS generated consistency; {len(before)} outputs; source untouched')


def owned_http_origin(wordpress):
    """Fixed-port fixtures may run only when their server serves this exact disposable directory."""
    name = f'cache-cache-http-proof-{os.urandom(8).hex()}.txt'
    path = wordpress / name
    token = os.urandom(24).hex()
    path.write_text(token, encoding='utf-8')
    origin = 'http://127.0.0.1:8765'
    try:
        with urlopen(f'{origin}/{name}', timeout=5) as response:
            if response.read(256).decode('utf-8') != token:
                raise CheckFailure('Port 8765 does not serve the selected disposable site; protected previews must not be tested')
    except OSError as error:
        raise CheckFailure('HTTP fixtures need an owned server for this copied site on port 8765') from error
    finally:
        path.unlink()
    return origin


@contextmanager
def native_http_boundary(wordpress, origin=None):
    """Block real HTTP by default; keep synthetic fixture responses and one verified owned origin."""
    path = wordpress / 'wp-content/mu-plugins/cache-cache-check-http.php'
    path.parent.mkdir(parents=True, exist_ok=True)
    if path.exists():
        raise CheckFailure(f'Refusing to replace existing fixture: {path}')
    allowed = json.dumps(origin or '')
    script = f"""<?php
add_filter('pre_http_request', function ($pre, $args, $url) {{
    if (false !== $pre) {{ return $pre; }}
    $origin = {allowed};
    if ($origin && strpos($url, $origin . '/') === 0) {{ return false; }}
    return new WP_Error('cache_cache_check_http', 'HTTP blocked by disposable fixture.');
}}, PHP_INT_MAX, 3);
"""
    path.write_text(script, encoding='utf-8')
    try:
        yield
    finally:
        if path.is_file() and path.read_text(encoding='utf-8') == script:
            path.unlink()


def fixtures(root, args):
    entries = catalog(root)
    requested = set(args.fixture or [])
    if requested:
        unknown = requested - {entry['name'] for entry in entries}
        if unknown:
            raise CheckFailure(f'Unknown fixtures: {sorted(unknown)}')
        selected = [entry for entry in entries if entry['name'] in requested]
        if any(entry['profile'] != args.profile or entry.get('kind') == 'helper' for entry in selected):
            raise CheckFailure('Fixture belongs to another profile or is a setup/helper, not a standalone check')
    else:
        selected = [entry for entry in entries if entry['profile'] == args.profile and entry['default']]
    if not selected:
        raise CheckFailure(f'No executable fixtures selected for {args.profile}')
    prerequisites = {value for entry in selected for value in entry['prerequisites']}
    variables = {'root': str(root)}
    if 'php' in prerequisites or any(entry['path'].endswith('.php') for entry in selected):
        variables['php'] = executable(args.php, 'PHP')
    if 'node' in prerequisites or 'node22' in prerequisites or any(entry['path'].endswith('.mjs') for entry in selected):
        variables['node'] = executable(args.node, 'Node')
    env = dict(os.environ)
    if 'php' in variables:
        env['NOOBROCKET_TEST_PHP'] = variables['php']
        php_version = subprocess.check_output([variables['php'], '-r', 'echo PHP_VERSION;'], text=True).strip()
        required_php = max((entry.get('minimum_php', '7.4') for entry in selected), key=lambda version: tuple(map(int, version.split('.'))))
        if tuple(map(int, php_version.split('.')[:2])) < tuple(map(int, required_php.split('.'))):
            raise CheckFailure(f'Selected fixture requires PHP {required_php}+; found {php_version}. Select the declared minimum compatibility scenarios explicitly.')
        print(f'Environment PHP {php_version}; {len(selected)} selected fixtures')
    if 'node' in variables:
        env['NOOBROCKET_TEST_NODE'] = variables['node']
    if 'chromium' in prerequisites:
        variables['chromium'] = executable(args.chromium, 'Chromium')
        if hasattr(os, 'geteuid') and os.geteuid() == 0:
            raise CheckFailure('Run browser fixtures as an unprivileged user; Chromium sandbox must stay enabled')
    if 'node22' in prerequisites:
        node_version = subprocess.check_output([variables['node'], '--version'], text=True).strip()
        if int(node_version.lstrip('v').split('.')[0]) < 22:
            raise CheckFailure(f'Browser/worker checks need Node 22+; found {node_version}')
        print(f'Environment Node {node_version}; Chromium {variables.get("chromium", "not selected")}')
    if 'worker-dependencies' in prerequisites and not (root / 'noobrocket/local-css/node_modules/playwright-core').is_dir():
        raise CheckFailure('Browser checks need: npm ci --prefix noobrocket/local-css')
    native = any(entry['environment'] == 'native-wordpress' for entry in selected)
    wordpress = None
    if 'wordpress-core' in prerequisites or native:
        if not args.wordpress:
            raise CheckFailure('Selected checks require explicit --wordpress PATH')
        wordpress = Path(args.wordpress).resolve()
        if not (wordpress / 'wp-load.php').is_file():
            raise CheckFailure('WordPress source missing wp-load.php')
        variables['wordpress'] = str(wordpress)
        env['NOOBROCKET_WORDPRESS_SOURCE'] = str(wordpress)
        version_file = wordpress / 'wp-includes/version.php'
        version = re.search(r"\$wp_version\s*=\s*'([^']+)'", version_file.read_text(encoding='utf-8'))
        print(f'Environment WordPress {version[1] if version else "unknown"}; source {wordpress}')
        if native:
            installed = wordpress / 'wp-content/plugins/noobrocket'
            if not (wordpress / '.noobrocket-acceptance-site').is_file():
                raise CheckFailure('Native fixtures require .noobrocket-acceptance-site in a disposable installation')
            if installed.resolve() == (root / 'noobrocket').resolve() or installed.is_symlink() or getattr(installed, 'is_junction', lambda: False)():
                raise CheckFailure('Native fixtures require a separate plugin copy; source junctions/symlinks are refused')
            if not (installed / 'noobrocket.php').is_file():
                raise CheckFailure('Disposable installed plugin copy missing')
    def execute():
        for entry in selected:
            command = [variables['php'] if entry['path'].endswith('.php') else variables['node'] if entry['path'].endswith('.mjs') else sys.executable, str(root / entry['path'])]
            command.extend(value.format(**variables) for value in entry['args'])
            run(command, root, entry['name'], env, entry.get('timeout', 300))
    if native:
        origin = owned_http_origin(wordpress) if 'loopback-http-8765' in prerequisites else None
        with native_http_boundary(wordpress, origin):
            execute()
    else:
        execute()
    print(f'PASS {args.profile}; {len(selected)} isolated serial checks; native={native}')


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('profile', choices=PROFILES)
    parser.add_argument('--php', default='php')
    parser.add_argument('--node', default='node')
    parser.add_argument('--shell', default='sh', help='POSIX shell for syntax checks; use Git sh explicitly on Windows')
    parser.add_argument('--wordpress', type=Path)
    parser.add_argument('--chromium')
    parser.add_argument('--fixture', action='append', help='Exact catalog name; repeat to select checks in this profile')
    parser.add_argument('--list', action='store_true', help='Print catalog entries for this profile without executing')
    parser.add_argument('--zip', type=Path, help='Existing package to verify (never overwritten)')
    parser.add_argument('--compare-source', action='store_true', help='Require ZIP bytes to equal maintained source')
    parser.add_argument('--wordpress-zip', type=Path, help='WordPress ZIP input for the owned Linux server proof')
    args = parser.parse_args(argv)
    root = ROOT
    try:
        if args.fixture and args.profile not in {'fast', 'wordpress', 'browser'}:
            raise CheckFailure('--fixture is supported only by fast, wordpress and browser profiles')
        if args.list:
            for entry in catalog(root):
                if entry['profile'] == args.profile:
                    print(f'{entry["name"]}: default={entry["default"]}; {entry["environment"]}; {entry["mutation"]}; needs={",".join(entry["prerequisites"])}')
        elif args.profile == 'docs':
            docs(root)
        elif args.profile == 'syntax':
            syntax(root, args.php, args.node, args.shell)
        elif args.profile == 'generated':
            generated(root, args.node)
        elif args.profile == 'package':
            if not args.zip or not args.zip.is_file():
                raise CheckFailure('Package check requires --zip PATH to an existing archive')
            command = [sys.executable, str(root / 'tools/verify-package.py'), str(args.zip.resolve())]
            if args.compare_source:
                command.extend(['--source', str(root / 'noobrocket')])
            run(command, root, 'package')
        elif args.profile == 'server':
            if sys.platform != 'linux' or not args.wordpress_zip or not args.wordpress_zip.is_file():
                raise CheckFailure('Server profile requires Linux and --wordpress-zip PATH; see development guide')
            env = dict(os.environ, PHP_CLI=executable(args.php, 'PHP'))
            run(['sh', str(root / 'tools/linux-production-proof.sh'), str(root), str(args.wordpress_zip.resolve()), executable(args.node, 'Node'), executable(args.chromium, 'Chromium')], root, 'server', env, 1800)
        else:
            fixtures(root, args)
    except (CheckFailure, OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
        print(f'FAIL {args.profile}: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
