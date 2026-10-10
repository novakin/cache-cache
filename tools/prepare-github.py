"""Prepare a curated migration checkout with the upstream release as its parent."""
from argparse import ArgumentParser
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
BASELINE = '8e89a5917009777d51669c9f3e76cf8f85401a6b'
CONTENT = (
    '.gitignore', '.gitattributes', '.github', 'AGENTS.md', 'CONTRIBUTING.md', 'README.md',
    'docs', 'noobrocket', 'tests', 'tools',
)


def git(directory, *arguments):
    return subprocess.check_output(
        ['git', '-C', str(directory), *arguments], text=True, encoding='utf-8',
        stderr=subprocess.STDOUT,
    ).strip()


def main():
    parser = ArgumentParser(description=__doc__)
    parser.add_argument('--upstream', type=Path, required=True)
    parser.add_argument('--destination', type=Path, required=True)
    parser.add_argument('--branch', default='cache-cache/migration')
    args = parser.parse_args()
    upstream = args.upstream.resolve(strict=True)
    destination = args.destination.resolve()
    if destination.exists():
        parser.error('Destination must be new; existing worktrees are never replaced.')
    if upstream == destination or upstream in destination.parents:
        parser.error('Destination must be outside the upstream checkout.')
    if destination == ROOT or any(
        source.is_dir() and (source == destination or source in destination.parents)
        for source in (ROOT / name for name in CONTENT)
    ):
        parser.error('Destination must be outside the maintained input trees.')
    if git(upstream, 'rev-parse', 'v3.23.5.1^{commit}') != BASELINE:
        parser.error('Upstream v3.23.5.1 does not match the recorded engine baseline.')
    git(upstream, 'check-ref-format', '--branch', args.branch)
    missing = [name for name in CONTENT if not (ROOT / name).exists()]
    if missing:
        parser.error('Required migration input missing: ' + ', '.join(missing))

    destination.parent.mkdir(parents=True, exist_ok=True)
    subprocess.run(
        ['git', 'clone', '--no-hardlinks', '--no-checkout', '--branch',
         'v3.23.5.1', str(upstream), str(destination)], check=True,
        stdout=subprocess.DEVNULL,
    )
    # Populate only the index: old upstream paths become intentional deletions.
    git(destination, 'branch', args.branch, BASELINE)
    git(destination, 'symbolic-ref', 'HEAD', 'refs/heads/' + args.branch)
    git(destination, 'read-tree', BASELINE)
    git(destination, 'remote', 'rename', 'origin', 'upstream')
    git(destination, 'remote', 'set-url', 'upstream', 'https://github.com/wp-media/wp-rocket.git')
    for name in CONTENT:
        source = ROOT / name
        target = destination / name
        if source.is_dir():
            shutil.copytree(source, target, ignore=shutil.ignore_patterns(
                'node_modules', '__pycache__', '*.pyc', '.venv', '.artifacts',
                '.git', '.env', '.env.*',
            ))
        else:
            shutil.copy2(source, target)
    git(destination, 'add', '--all')
    print('Prepared migration checkout:', destination)
    print('Branch:', args.branch, '| parent:', BASELINE)
    print('Staged delta:', git(destination, 'diff', '--cached', '--shortstat'))
    print('No commit, push, fork creation or runtime change performed.')


if __name__ == '__main__':
    main()
