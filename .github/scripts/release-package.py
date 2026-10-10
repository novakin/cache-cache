"""Manual readiness proof; never publish or overwrite a distributable."""
import hashlib
import os
from pathlib import Path
import re
import subprocess
import sys

version = os.environ['RELEASE_VERSION']
digest = os.environ['EXPECTED_SHA256'].lower()
if not re.fullmatch(r'0\.\d+\.\d+-beta\.\d+', version):
    sys.exit('FAIL release: an exact pre-1.0 beta version is required')
if not re.fullmatch(r'[0-9a-f]{64}', digest):
    sys.exit('FAIL release: expected SHA-256 must contain 64 hexadecimal characters')
header = Path('noobrocket/noobrocket.php').read_text(encoding='utf-8')
if not re.search(r'^ \* Version: ' + re.escape(version) + r'$', header, re.MULTILINE):
    sys.exit('FAIL release: input version differs from checked-out plugin version')
archive = Path(sys.argv[1])
subprocess.run([sys.executable, 'tools/build.py', '--output', str(archive)], check=True)
actual = hashlib.sha256(archive.read_bytes()).hexdigest()
if actual != digest:
    sys.exit(f'FAIL release: package SHA-256 differs from reviewed value; actual {actual}')
subprocess.run([sys.executable, 'tools/check.py', 'package', '--zip', str(archive), '--compare-source'], check=True)
revision = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()
with open(os.environ['GITHUB_STEP_SUMMARY'], 'a', encoding='utf-8') as stream:
    stream.write(f'Verified beta `{version}` on `{revision}`.\n\nSHA-256: `{actual}`\n\nPublication and deployment were not performed.\n')
