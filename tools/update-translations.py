"""Add missing literal plugin labels, retain translations, and compile catalogs."""
from argparse import ArgumentParser
from collections import Counter
from gettext import GNUTranslations
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
try:
    import polib
except ImportError as error:
    raise SystemExit('Install polib==1.2.0 before updating translations.') from error

SOURCE = ROOT / 'noobrocket'
LANGUAGES = SOURCE / 'languages'
LITERAL = r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\""
TRANSLATION = re.compile(
    r'\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\s*\(\s*('
    + LITERAL + r')\s*,\s*[\'\"]noobrocket[\'\"]\s*\)',
    re.DOTALL,
)
PLURAL = re.compile(
    r'\b_n\s*\(\s*(' + LITERAL + r')\s*,\s*(' + LITERAL
    + r')\s*,\s*[^,]+,\s*[\'\"]noobrocket[\'\"]\s*\)', re.DOTALL,
)
PLACEHOLDER = re.compile(r'%%|%(?:(\d+)\$)?([a-zA-Z])')


def printf_placeholders(value):
    """Normalize implicit argument positions while respecting escaped percent signs."""
    position = 0
    placeholders = []
    for match in PLACEHOLDER.finditer(value):
        if match[0] == '%%':
            continue
        if match[1]:
            argument = int(match[1])
        else:
            position += 1
            argument = position
        placeholders.append((argument, match[2]))
    return Counter(placeholders)


def validate_placeholders(catalog, path):
    """Reject unsafe active translations; leave reviewed fuzzy translations as fallback."""
    for entry in catalog:
        if entry.obsolete or 'fuzzy' in entry.flags:
            continue
        sources = [entry.msgid]
        if entry.msgid_plural:
            sources.append(entry.msgid_plural)
        allowed = [printf_placeholders(source) for source in sources]
        translations = entry.msgstr_plural.values() if entry.msgid_plural else [entry.msgstr]
        for translated in translations:
            if translated and printf_placeholders(translated) not in allowed:
                raise SystemExit(f'Translation placeholders differ: {path.name}: {entry.msgid}')


def verify_compiled(catalog, compiled, path):
    """Prove singular, contextual and plural lookups match the authoritative PO."""
    verified = 0
    for entry in catalog:
        if entry.obsolete:
            continue
        if entry.msgid_plural:
            for number in (0, 1, 2, 5, 10, 100, 1000000):
                expected = (
                    entry.msgstr_plural[compiled.plural(number)]
                    if entry.translated()
                    else entry.msgid if number == 1 else entry.msgid_plural
                )
                actual = (
                    compiled.npgettext(entry.msgctxt, entry.msgid, entry.msgid_plural, number)
                    if entry.msgctxt
                    else compiled.ngettext(entry.msgid, entry.msgid_plural, number)
                )
                if actual != expected:
                    raise SystemExit(f'Compiled plural lookup failed: {path.name}: {entry.msgid}')
                verified += 1
        else:
            expected = entry.msgstr if entry.translated() else entry.msgid
            actual = compiled.pgettext(entry.msgctxt, entry.msgid) if entry.msgctxt else compiled.gettext(entry.msgid)
            if actual != expected:
                raise SystemExit(f'Compiled lookup failed: {path.name}: {entry.msgid}')
            verified += 1
    return verified

def php_literal(literal):
    """Decode literal PHP strings without interpreting unknown escapes."""
    value = literal[1:-1]
    if literal[0] == "'":
        return re.sub(r"\\([\\'])", lambda match: match[1], value)
    escapes = {'\\': '\\', '"': '"', 'n': '\n', 'r': '\r', 't': '\t', 'v': '\v', 'f': '\f', 'e': '\x1b', '$': '$'}
    return re.sub(r'\\([\\"nrtvfe$])', lambda match: escapes[match[1]], value)


def source_labels():
    """Supplement existing catalogs; this is not a replacement for full gettext extraction."""
    labels = {}
    for path in sorted(SOURCE.rglob('*.php')):
        relative = path.relative_to(SOURCE)
        if relative.parts[0] == 'vendor' or relative.parts[:2] == ('inc', 'Dependencies'):
            continue
        text = path.read_text(encoding='utf-8-sig')
        for match in TRANSLATION.finditer(text):
            msgid = php_literal(match[1])
            line = text.count('\n', 0, match.start()) + 1
            labels.setdefault(msgid, []).append((relative.as_posix(), str(line)))
    header = (SOURCE / 'noobrocket.php').read_text(encoding='utf-8')
    for field in ('Plugin Name', 'Description', 'Author'):
        match = re.search(r'^ \* ' + field + r': (.+)$', header, re.MULTILINE)
        labels.setdefault(match[1], []).append(('noobrocket.php', str(header.count('\n', 0, match.start()) + 1)))
    return labels


def source_plurals():
    labels = {}
    for path in sorted(SOURCE.rglob('*.php')):
        relative = path.relative_to(SOURCE)
        if relative.parts[0] == 'vendor' or relative.parts[:2] == ('inc', 'Dependencies'):
            continue
        text = path.read_text(encoding='utf-8-sig')
        for match in PLURAL.finditer(text):
            singular, plural = php_literal(match[1]), php_literal(match[2])
            labels[singular] = (plural, [(relative.as_posix(), str(text.count('\n', 0, match.start()) + 1))])
    return labels


def main():
    parser = ArgumentParser(description=__doc__)
    parser.add_argument('--version', help='Catalog version; defaults to the current plugin header.')
    args = parser.parse_args()
    header = (SOURCE / 'noobrocket.php').read_text(encoding='utf-8')
    version = args.version or re.search(r'^ \* Version: (\S+)$', header, re.MULTILINE)[1]
    product = re.search(r'^ \* Plugin Name: (.+)$', header, re.MULTILINE)[1]
    labels = source_labels()
    plurals = source_plurals()
    template_path = LANGUAGES / 'noobrocket.pot'
    template = polib.pofile(str(template_path))
    missing = [msgid for msgid in labels if template.find(msgid) is None]
    for msgid in missing:
        template.append(polib.POEntry(msgid=msgid, occurrences=labels[msgid]))
    for msgid, (plural, occurrences) in plurals.items():
        if template.find(msgid) is None:
            template.append(polib.POEntry(msgid=msgid, msgid_plural=plural, occurrences=occurrences))
    template.metadata['Project-Id-Version'] = f'{product} {version}'
    template.metadata['X-Generator'] = f'{product} translation updater (polib {polib.__version__})'
    template.save()
    catalogs = sorted(LANGUAGES.glob('*.po'))
    verified = 0
    for path in catalogs:
        catalog = polib.pofile(str(path))
        for msgid in labels:
            if catalog.find(msgid) is None:
                catalog.append(polib.POEntry(msgid=msgid, occurrences=labels[msgid]))
        for msgid, (plural, occurrences) in plurals.items():
            if catalog.find(msgid) is None:
                count = int(re.search(r'nplurals=(\d+)', catalog.metadata['Plural-Forms'])[1])
                catalog.append(polib.POEntry(msgid=msgid, msgid_plural=plural, occurrences=occurrences, msgstr_plural={index: '' for index in range(count)}))
        catalog.metadata['Project-Id-Version'] = f'{product} {version}'
        catalog.metadata['X-Generator'] = template.metadata['X-Generator']
        validate_placeholders(catalog, path)
        catalog.save()
        mo_path = path.with_suffix('.mo')
        catalog.save_as_mofile(str(mo_path))
        with mo_path.open('rb') as file:
            compiled = GNUTranslations(file)
        if compiled.info()['project-id-version'] != f'{product} {version}':
            raise SystemExit(f'Wrong compiled version: {mo_path.name}')
        verified += verify_compiled(catalog, compiled, path)
    print(f'Updated POT and {len(catalogs)} PO/MO catalogs to {version}; {len(missing)} new labels; {verified} compiled lookups verified against PO translations and English fallback.')


if __name__ == '__main__':
    main()
