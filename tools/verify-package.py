"""Verify an existing plugin archive; never rebuild or overwrite it."""
import argparse
from gettext import GNUTranslations
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
from zipfile import ZipFile


def verify(archive_path, source=None):
    archive_path = Path(archive_path)
    with ZipFile(archive_path) as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise ValueError('Duplicate archive entries')
        for name in names:
            path = PurePosixPath(name)
            if not name.startswith('noobrocket/') or '\\' in name or '..' in path.parts or path.is_absolute():
                raise ValueError(f'Unsafe archive path: {name}')
            if any(part in {'node_modules', '.artifacts', '.git', '__pycache__'} for part in path.parts):
                raise ValueError(f'Runtime/development directory in archive: {name}')
            if path.suffix in {'.sqlite', '.sql', '.log'} or path.name in {'.env', 'wp-config.php'} or path.name.startswith('.env.'):
                raise ValueError(f'Runtime/private file in archive: {name}')
        invalid = archive.testzip()
        if invalid:
            raise ValueError(f'Corrupt archive entry: {invalid}')
        header = archive.read('noobrocket/noobrocket.php').decode('utf-8')
        version_match = re.search(r'^ \* Version: (\S+)$', header, re.MULTILINE)
        if not version_match or 'Plugin Name: Cache-Cache' not in header:
            raise ValueError('Missing Cache-Cache plugin metadata')
        version = version_match[1]
        constant = re.search(r"define\(\s*'NOOBROCKET_VERSION',\s*'([^']+)'\s*\)", header)
        engine = re.search(r"define\(\s*'NOOBROCKET_ENGINE_VERSION',\s*'([^']+)'\s*\)", header)
        stable = re.search(r'^Stable tag:\s*(\S+)', archive.read('noobrocket/readme.txt').decode('utf-8'), re.MULTILINE)
        if not constant or constant[1] != version or not stable or stable[1] != version:
            raise ValueError('Product constant/readme version differs from header')
        if not engine or not re.fullmatch(r'\d+\.\d+\.\d+(?:\.\d+)?', engine[1]):
            raise ValueError('Missing independent engine version')
        manifest = json.loads(archive.read('noobrocket/local-css/package.json'))
        lock = json.loads(archive.read('noobrocket/local-css/package-lock.json'))
        if manifest['version'] != version or lock['version'] != version or lock['packages']['']['version'] != version:
            raise ValueError('Worker manifest/lock version differs from plugin')
        catalogs = [name for name in names if name.endswith('.mo') and '/languages/' in name]
        if not catalogs:
            raise ValueError('No compiled catalogs')
        for name in catalogs:
            with archive.open(name) as stream:
                if GNUTranslations(stream).info().get('project-id-version') != f'Cache-Cache {version}':
                    raise ValueError(f'Catalog version differs: {name}')
        if source:
            source = Path(source)
            expected = {
                'noobrocket/' + path.relative_to(source).as_posix(): path
                for path in source.rglob('*')
                if path.is_file() and 'node_modules' not in path.relative_to(source).parts and path.name != '.env' and not path.name.startswith('.env.')
            }
            if set(names) != set(expected):
                missing = sorted(set(expected) - set(names))[:5]
                extra = sorted(set(names) - set(expected))[:5]
                raise ValueError(f'Archive/source inventory differs; missing={missing}, extra={extra}')
            for name, path in expected.items():
                if archive.read(name) != path.read_bytes():
                    raise ValueError(f'Archive/source bytes differ: {name}')
    digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    return f'{version}; {len(names)} entries; {len(catalogs)} catalogs; SHA-256 {digest}'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', type=Path)
    parser.add_argument('--source', type=Path, help='Require every archive byte to match this plugin directory')
    args = parser.parse_args()
    try:
        print('PASS package:', verify(args.archive, args.source))
    except (OSError, ValueError, KeyError) as error:
        parser.exit(1, f'FAIL package: {error}\n')


if __name__ == '__main__':
    main()
