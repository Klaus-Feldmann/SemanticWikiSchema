#!/usr/bin/env python3
"""Exact-base, reversible SWS skill-profile patch for MediaWiki MCP 0.21.0."""
import argparse
import datetime
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import sys
import tempfile

ROOT = Path(__file__).resolve().parent


def digest(path):
    if path.is_symlink():
        raise ValueError(f'Symbolischer Dateilink wird nicht verändert: {path}')
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else None


def safe_path(root, name):
    relative = PurePosixPath(name)
    if relative.is_absolute() or '..' in relative.parts or not relative.parts:
        raise ValueError(f'Ungültiger Paketpfad: {name}')
    path = root.joinpath(*relative.parts)
    for parent in [path, *path.parents]:
        if parent == root:
            break
        if parent.is_symlink():
            raise ValueError(f'Symbolischer Link im Zielpfad: {parent}')
    if not path.resolve().is_relative_to(root.resolve()):
        raise ValueError(f'Pfad außerhalb des Zielordners: {name}')
    return path


def load_manifest():
    manifest = json.loads((ROOT / 'manifest.json').read_text())
    for name, entry in manifest['changes'].items():
        payload = safe_path(ROOT / 'payload', name)
        if digest(payload) != entry['after']:
            raise ValueError(f'Paketdatei fehlt oder wurde geändert: {name}')
    return manifest


def state(target, manifest):
    """Require a complete old or complete new state; never install a mixed tree."""
    target = target.resolve(strict=True)
    old_errors, new_errors = [], []
    names = set(manifest['baseline']) | set(manifest['changes'])
    for name in sorted(names):
        actual = digest(safe_path(target, name))
        before = manifest['baseline'].get(name)
        after = manifest['changes'].get(name, {}).get('after', before)
        if actual != before:
            old_errors.append(name)
        if actual != after:
            new_errors.append(name)
    if not new_errors:
        return 'installed'
    if old_errors:
        raise ValueError('Abweichender Ausgangsstand; nichts geändert: ' + ', '.join(old_errors[:12]))
    return 'base'


def atomic_copy(source, target, mode=None):
    target.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.NamedTemporaryFile(dir=target.parent, delete=False) as stream:
        temporary = Path(stream.name)
    try:
        shutil.copy2(source, temporary)
        if mode is not None:
            temporary.chmod(mode)
        os.replace(temporary, target)
    finally:
        temporary.unlink(missing_ok=True)


def restore_files(target, backup, journal):
    for name, entry in journal['changes'].items():
        path = safe_path(target, name)
        if entry['before'] is None:
            path.unlink(missing_ok=True)
        else:
            atomic_copy(safe_path(backup / 'original', name), path, entry['mode'])
    # Only empty directories can disappear; unrelated files are preserved.
    directories = set()
    for name in journal['changes']:
        directories.update(safe_path(target, name).parents)
    for directory in sorted(directories, key=lambda item: len(item.parts), reverse=True):
        if directory == target or not directory.is_relative_to(target):
            continue
        try:
            directory.rmdir()
        except OSError:
            pass


def install(target, manifest, backup_root):
    target = target.resolve(strict=True)
    if state(target, manifest) == 'installed':
        print('Dieser Patch ist bereits vollständig installiert. Keine Änderung.')
        return
    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%S.%fZ')
    backup = backup_root / timestamp
    backup.mkdir(parents=True, exist_ok=False)
    journal = {'target': str(target), 'patch': manifest['patchId'], 'changes': {}}
    for name, entry in manifest['changes'].items():
        path = safe_path(target, name)
        mode = path.stat().st_mode & 0o777 if path.exists() else 0o644
        journal['changes'][name] = {**entry, 'mode': mode}
        if entry['before'] is not None:
            destination = safe_path(backup / 'original', name)
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, destination)
    (backup / 'journal.json').write_text(json.dumps(journal, indent=2) + '\n')
    # Recheck every baseline file after backup and before the first mutation.
    if state(target, manifest) != 'base':
        raise ValueError('Der Zielstand änderte sich während der Vorbereitung; nichts geändert.')
    completed = []
    try:
        for name, entry in journal['changes'].items():
            atomic_copy(safe_path(ROOT / 'payload', name), safe_path(target, name), entry['mode'])
            completed.append(name)
        if state(target, manifest) != 'installed':
            raise ValueError('Prüfung nach Installation fehlgeschlagen.')
    except BaseException:
        restore_files(target, backup, {**journal, 'changes': {name: journal['changes'][name] for name in completed}})
        raise
    print(f'Patch installiert und geprüft. Sicherung: {backup}')
    print('MCP-Verbindung jetzt neu starten. SWS/rebuildData bleiben unverändert.')


def rollback(target, backup, dry_run=False):
    target = target.resolve(strict=True)
    journal = json.loads((backup / 'journal.json').read_text())
    if Path(journal['target']).resolve() != target:
        raise ValueError('Sicherung gehört zu einem anderen Zielordner.')
    for name, entry in journal['changes'].items():
        if digest(safe_path(target, name)) != entry['after']:
            raise ValueError(f'Zieldatei wurde seit Installation verändert; Rücknahme verweigert: {name}')
        if entry['before'] is not None and digest(safe_path(backup / 'original', name)) != entry['before']:
            raise ValueError(f'Sicherung fehlt oder wurde verändert: {name}')
    if dry_run:
        print('Rücknahme ist möglich; nichts geändert.')
        return
    restore_files(target, backup, journal)
    for name, entry in journal['changes'].items():
        if digest(safe_path(target, name)) != entry['before']:
            raise ValueError(f'Prüfung nach Rücknahme fehlgeschlagen: {name}')
    print('Ursprünglicher Stand wiederhergestellt. MCP-Verbindung neu starten.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--ziel', type=Path, required=True, help='Ordner des installierten MCP-NPM-Pakets')
    parser.add_argument('--anwenden', action='store_true', help='Patch nach vollständiger Prüfung installieren')
    parser.add_argument('--sicherungen', type=Path, help='Optionaler Ordner für Sicherungen außerhalb des Zielpakets')
    parser.add_argument('--wiederherstellen', type=Path, help='Sicherungsordner mit journal.json zurückspielen')
    parser.add_argument('--pruefen', action='store_true', help='Nur prüfen (Vorgabe ohne --anwenden)')
    args = parser.parse_args()
    if args.anwenden and (args.pruefen or args.wiederherstellen):
        parser.error('--anwenden kann nicht mit --pruefen/--wiederherstellen kombiniert werden')
    target = args.ziel.resolve(strict=True)
    if not target.is_dir():
        raise ValueError('Ziel muss ein vorhandener Paketordner sein.')
    if args.wiederherstellen:
        rollback(target, args.wiederherstellen.resolve(strict=True), args.pruefen)
        return
    manifest = load_manifest()
    current = state(target, manifest)
    if not args.anwenden:
        print(f'Ausgangsstand geprüft: {current}; {len(manifest["changes"])} Patchdateien. Nichts geändert.')
        return
    backup_root = (args.sicherungen or target.parent / '.sws-patch-sicherungen').resolve()
    if backup_root.is_relative_to(target):
        raise ValueError('Sicherungsordner muss außerhalb des Zielpakets liegen.')
    install(target, manifest, backup_root)


if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, KeyError) as error:
        print(f'Abgebrochen: {error}', file=sys.stderr)
        sys.exit(1)
