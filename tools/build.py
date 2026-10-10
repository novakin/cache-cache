"""Build the installable WordPress plugin from the maintained noobrocket source."""
from pathlib import Path
from argparse import ArgumentParser
import re
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo

root = Path(__file__).resolve().parents[1]
source = root / 'noobrocket'
header = (source / 'noobrocket.php').read_text(encoding='utf-8')
version = re.search(r'^ \* Version: (\S+)$', header, re.MULTILINE).group(1)
parser = ArgumentParser(description=__doc__)
parser.add_argument('--output', type=Path, help='Destination ZIP; existing paths are refused')
args = parser.parse_args()
destination = args.output or root / 'dist' / f'cache-cache-{version}.zip'
if destination.exists():
    raise SystemExit(f'Refusing to replace existing package: {destination}. Use a new beta version or a new output path.')
destination.parent.mkdir(parents=True, exist_ok=True)
files = sorted(path for path in source.rglob('*') if path.is_file() and 'node_modules' not in path.relative_to(source).parts and path.name != '.env' and not path.name.startswith('.env.'))
with ZipFile(destination, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path in files:
        # Stable timestamps/permissions keep a release checksum independent of checkout OS and mtimes.
        entry = ZipInfo(path.relative_to(root).as_posix(), date_time=(1980, 1, 1, 0, 0, 0))
        entry.compress_type = ZIP_DEFLATED
        entry.create_system = 3
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, path.read_bytes(), compresslevel=9)
with ZipFile(destination) as archive:
    invalid = archive.testzip()
    if invalid:
        raise SystemExit(f'Corrupt archive entry: {invalid}')
    assert 'noobrocket/noobrocket.php' in archive.namelist()
    assert all(name.startswith('noobrocket/') for name in archive.namelist())
print(f'Built {destination.name}: {len(files)} files, {destination.stat().st_size:,} bytes; archive verified.')
