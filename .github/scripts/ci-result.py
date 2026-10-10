"""A required check always resolves, including explicit irrelevant heavy groups."""
import json
import os
import sys

needs = json.loads(os.environ['CHECK_RESULTS'])
failed = []
lines = ['| Group | Applicable | Result |', '| --- | --- | --- |']
for name in ('scope', 'fast', 'wordpress', 'browser', 'server'):
    applicable = name in ('scope', 'fast') or needs['scope']['outputs'].get(name) == 'true'
    result = needs[name]['result']
    expected = 'success' if applicable else 'skipped'
    if result != expected:
        failed.append(name)
    lines.append(f'| {name} | {"yes" if applicable else "no"} | {result} |')
with open(os.environ['GITHUB_STEP_SUMMARY'], 'a', encoding='utf-8') as stream:
    stream.write('\n'.join(lines) + '\n')
print('\n'.join(lines))
if failed:
    sys.exit('FAIL applicable CI groups: ' + ', '.join(failed))
print('PASS applicable CI groups; skipped groups were not counted as passes')
