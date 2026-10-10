"""Reconstruct the shipped prefixed dependencies from pinned release source.

This does not install Composer packages or rebuild their original Mozart inputs.
Output always goes to a new staging directory, never the maintained distribution.
"""
from argparse import ArgumentParser
from hashlib import sha256
from io import BytesIO
import json
from pathlib import Path, PurePosixPath
import re
import subprocess


ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / 'tools/php-dependencies/manifest.json'


def inventory(directory, scopes):
    files = {}
    for scope in scopes:
        owner = directory / scope
        if not owner.is_dir() or owner.is_symlink():
            raise ValueError(f'Missing or linked dependency owner: {owner}')
        for path in owner.rglob('*'):
            if path.is_symlink():
                raise ValueError(f'Linked dependency input: {path}')
            if path.is_file():
                files[path.relative_to(directory).as_posix()] = path.read_bytes()
    return files


def tree_hash(files):
    digest = sha256()
    for name, data in sorted(files.items()):
        digest.update(name.encode('utf-8') + b'\0' + sha256(data).digest() + b'\n')
    return digest.hexdigest()


def verify(files, manifest):
    expected = manifest['output']
    if len(files) != expected['files'] or tree_hash(files) != expected['sha256']:
        raise ValueError('Dependency bytes differ from the pinned reconstructed bundle.')


def git(repository, *arguments, input_bytes=None):
    result = subprocess.run(
        ['git', '-C', str(repository), *arguments], input=input_bytes, capture_output=True, check=False,
    )
    if result.returncode:
        detail = result.stderr.decode('utf-8', errors='replace').strip().splitlines()
        raise ValueError('Git source unavailable: ' + (detail[0] if detail else str(result.returncode)))
    return result.stdout


def reconstruct(repository, manifest):
    source = manifest['source']
    commit = source['commit']
    if git(repository, 'rev-parse', f'{commit}^{{commit}}').decode().strip() != commit:
        raise ValueError('Source commit differs from the manifest.')
    for scope, expected in source['trees'].items():
        actual = git(repository, 'rev-parse', f'{commit}:{scope}').decode().strip()
        if actual != expected:
            raise ValueError(f'Source tree differs: {scope}')

    replacements = {old.encode(): new.encode() for old, new in manifest['replacements'].items()}
    pattern = re.compile(b'|'.join(re.escape(old) for old in sorted(replacements, key=len, reverse=True)))
    entries = git(repository, 'ls-tree', '-r', '-z', commit, *source['trees']).split(b'\0')
    objects = []
    for entry in filter(None, entries):
        header, name = entry.split(b'\t', 1)
        mode, kind, object_id = header.split()
        path = PurePosixPath(name.decode('utf-8'))
        if mode not in (b'100644', b'100755') or kind != b'blob' or path.is_absolute() or '..' in path.parts:
            raise ValueError(f'Unexpected source entry: {path}')
        objects.append((path.as_posix(), object_id))
    # Git archive honors upstream export-ignore and drops a nested LICENSE.
    # Read immutable blobs directly so all declared license bytes are retained.
    batch = BytesIO(git(
        repository, 'cat-file', '--batch',
        input_bytes=b'\n'.join(object_id for _, object_id in objects) + b'\n',
    ))
    files = {}
    for name, object_id in objects:
        actual_id, kind, size = batch.readline().split()
        data = batch.read(int(size))
        if actual_id != object_id or kind != b'blob' or len(data) != int(size) or batch.read(1) != b'\n':
            raise ValueError(f'Git blob response differs: {name}')
        if name in manifest['omitted']:
            continue
        data = pattern.sub(lambda match: replacements[match.group()], data)
        target = name.replace('RocketLazyload', 'NoobRocketLazyload')
        if target in files:
            raise ValueError(f'Duplicate source destination: {target}')
        files[target] = data
    if len(objects) != source['files']:
        raise ValueError('Source inventory differs from the manifest.')
    for patch in manifest['patches']:
        name, before, after = patch['path'], patch['before'].encode(), patch['after'].encode()
        if files[name].count(before) != 1:
            raise ValueError(f'Patch preimage differs: {name}')
        files[name] = files[name].replace(before, after)
    verify(files, manifest)
    return files


def main():
    parser = ArgumentParser(description=__doc__)
    parser.add_argument('--source-repository', type=Path, default=ROOT)
    parser.add_argument('--destination', type=Path, help='New directory for inc/ dependency owners.')
    parser.add_argument('--verify-shipped', action='store_true', help='Verify pinned bytes without upstream Git history.')
    args = parser.parse_args()
    manifest = json.loads(MANIFEST.read_text(encoding='utf-8'))
    try:
        shipped = inventory(ROOT / 'noobrocket', manifest['source']['trees'])
        verify(shipped, manifest)
        if args.verify_shipped:
            if args.destination:
                raise ValueError('Choose verification or reconstruction, not both.')
            print(f'Pinned prefixed bundle verified: {len(shipped)} files; {tree_hash(shipped)}')
            return
        if not args.destination:
            raise ValueError('Reconstruction requires --destination.')
        destination = args.destination.resolve()
        repository = args.source_repository.resolve(strict=True)
        protected = [
            owner / name for owner in (ROOT, repository)
            for name in ('noobrocket', 'inc', 'vendor', 'tools', 'tests', 'docs', '.github', '.git')
        ]
        if destination.exists() or destination == ROOT or any(
            destination == owner or owner in destination.parents for owner in protected
        ):
            raise ValueError('Destination must be new and outside maintained inputs.')
        files = reconstruct(repository, manifest)
        if files != shipped:
            raise ValueError('Reconstructed files differ from the maintained distribution.')
        destination.mkdir(parents=True)
        for name, data in files.items():
            target = destination / name
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(data)
        verify(inventory(destination, manifest['source']['trees']), manifest)
        print(f'Reconstructed {len(files)} files; byte-identical to maintained dependencies.')
        print(f'Owned staging directory: {destination}')
    except (OSError, ValueError, subprocess.SubprocessError) as error:
        parser.exit(1, f'Dependency reconstruction failed: {error}\n')


if __name__ == '__main__':
    main()
