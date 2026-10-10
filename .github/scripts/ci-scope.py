"""Select existing check groups and record the integration revision."""
import json
import os
from pathlib import Path
import subprocess
import sys


def select(paths, full=False):
    # Docs/help and translation text do not change PHP execution. The fast job
    # still checks their links/catalogs; shipped code and fixture changes do.
    infrastructure = full or any(
        path in {'tests/catalog.json', 'tools/requirements.txt',
                 'tools/package.json', 'tools/package-lock.json',
                 'tools/build.py', 'tools/verify-package.py',
                 'tools/generate-settings-assets.mjs', 'tools/update-translations.py',
                 'noobrocket/composer.json', 'noobrocket/composer.lock',
                 '.gitattributes', '.gitignore'}
        or path.startswith(('.github/', 'tools/check', 'tools/verification', 'tools/php-quality/'))
        for path in paths
    )
    native = infrastructure or any(
        (path.startswith('noobrocket/') and path.endswith('.php'))
        or path.startswith('tests/wordpress-')
        for path in paths
    )
    browser = infrastructure or any(
        path.startswith(('noobrocket/assets/', 'noobrocket/views/', 'noobrocket/local-css/'))
        or (path.startswith('tests/') and path.endswith('.mjs'))
        or path.startswith(('noobrocket/inc/admin/', 'noobrocket/inc/Engine/Admin/Settings/',
                            'noobrocket/inc/Engine/Media/',
                            'noobrocket/inc/Engine/Optimization/', 'noobrocket/inc/Engine/LocalCSS/',
                            'noobrocket/inc/Engine/CDN/'))
        for path in paths
    )
    server = infrastructure or any(
        path.startswith(('noobrocket/inc/Engine/Cache/', 'noobrocket/inc/Engine/Preload/',
                         'noobrocket/inc/Engine/Optimization/', 'noobrocket/inc/Engine/LocalCSS/',
                         'noobrocket/inc/classes/Buffer/', 'noobrocket/inc/functions/',
                         'noobrocket/inc/Dependencies/', 'noobrocket/vendor/',
                         'noobrocket/local-css/', 'noobrocket/nginx/', 'noobrocket/inc/deprecated/'))
        or path in {'noobrocket/noobrocket.php', 'noobrocket/uninstall.php'}
        or path.startswith(('tools/linux-', 'tools/production-', 'tools/server-load',
                            'tests/linux-', 'tests/wordpress-cache-', 'tests/wordpress-local-css',
                            'tests/wordpress-stale-refresh'))
        for path in paths
    )
    return {'wordpress': native, 'browser': browser, 'server': server}


def git(*args):
    return subprocess.check_output(['git', *args], text=True, encoding='utf-8').strip()


def main():
    if '--self-test' in sys.argv:
        assert select(['docs/development.md']) == dict(wordpress=False, browser=False, server=False)
        assert select(['noobrocket/inc/admin/ui/enqueue.php']) == dict(wordpress=True, browser=True, server=False)
        for path in ('noobrocket/inc/Engine/Admin/Settings/Page.php',
                     'noobrocket/inc/Engine/Admin/Settings/Settings.php'):
            assert select([path]) == dict(wordpress=True, browser=True, server=False), f'Admin settings browser bypass: {path}'
        assert select(['noobrocket/inc/Engine/Cache/AdvancedCache.php']) == dict(wordpress=True, browser=False, server=True)
        assert select(['noobrocket/local-css/worker.mjs']) == dict(wordpress=False, browser=True, server=True)
        assert select(['noobrocket/inc/Engine/Optimization/DelayJS/Subscriber.php']) == dict(wordpress=True, browser=True, server=True)
        for path in ('tests/catalog.json', 'tools/requirements.txt', 'tools/package-lock.json',
                     'tools/build.py', 'tools/check.py', 'tools/php-quality/phpstan.neon',
                     'noobrocket/composer.lock', '.gitattributes'):
            assert all(select([path]).values()), f'Infrastructure check bypass: {path}'
        assert all(select([], True).values())
        print('PASS CI selection: docs, PHP/admin, cache, worker, catalog/toolchain and full checks')
        return
    event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text(encoding='utf-8'))
    candidate = git('rev-parse', 'HEAD')
    full = os.environ.get('FULL_CHECKS') == 'true'
    if 'pull_request' in event:
        head = event['pull_request']['head']['sha']
        base = event['pull_request']['base']['sha']
        parents = git('show', '-s', '--format=%P', candidate).split()
        if parents != [base, head]:
            raise RuntimeError('Checkout is not the event head/base merge candidate; rerun on the current PR revision')
    else:
        head = candidate
        base = event.get('before', '')
        if not base or base == '0' * 40:
            base = git('rev-parse', 'HEAD^')
            full = True
    paths = git('diff', '--name-only', base, head).splitlines()
    groups = select(paths, full)
    values = dict(groups, head=head, base=base, candidate=candidate)
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as stream:
        for key, value in values.items():
            stream.write(f'{key}={str(value).lower()}\n')
    with open(os.environ['GITHUB_STEP_SUMMARY'], 'a', encoding='utf-8') as stream:
        stream.write(f'Head: `{head}`\n\nBase: `{base}`\n\nTested integration candidate: `{candidate}`\n\n')
        stream.write('| Group | Applicable |\n| --- | --- |\n')
        for key, value in groups.items():
            stream.write(f'| {key} | {"yes" if value else "no"} |\n')
    print(f'Head {head}; base {base}; candidate {candidate}; groups {groups}')


if __name__ == '__main__':
    main()
