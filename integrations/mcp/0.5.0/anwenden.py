#!/usr/bin/env python3
"""SWS 0.5.0 patch for official MediaWiki MCP 0.21.0. Checks only unless --apply is supplied."""
import argparse
from datetime import datetime
import hashlib
import json
import os
from pathlib import Path
import shutil
import tempfile

ROOT = Path(__file__).resolve().parent

def digest(p):
    return hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None

def safe_path(root, name):
    relative = Path(name)
    if relative.is_absolute() or not relative.parts or '..' in relative.parts:
        raise ValueError('Invalid package path: ' + name)
    dest = root / relative
    for part in [dest, *dest.parents]:
        if part == root:
            break
        if part.is_symlink():
            raise ValueError('Symbolic links are not supported in patch paths: ' + name)
    if not dest.resolve().is_relative_to(root.resolve()):
        raise ValueError('Path escapes target: ' + name)
    if dest.exists() and not dest.is_file():
        raise ValueError('Not a regular file: ' + name)
    return dest

def atomic_write(dest, content, mode=0o644):
    dest.parent.mkdir(parents=True, exist_ok=True)
    fd, name = tempfile.mkstemp(prefix='.mcp-sws-', dir=dest.parent)
    try:
        with os.fdopen(fd, 'wb') as stream:
            stream.write(content)
        os.chmod(name, mode)
        os.replace(name, dest)
    finally:
        if os.path.exists(name):
            os.unlink(name)

def restore(target, backup):
    data = json.loads((backup / 'BACKUP.json').read_text())
    if data['target'] != str(target):
        raise ValueError('Backup belongs to a different target')
    plan = []
    for name, entry in data['files'].items():
        dest = safe_path(target, name)
        actual = digest(dest)
        if actual not in [entry['before'], entry['after']]:
            raise ValueError('Target has later changes; refusing rollback: ' + name)
        source = safe_path(backup / 'files', name)
        if digest(source) != entry['before']:
            raise ValueError('Backup checksum mismatch: ' + name)
        plan.append((dest, source, entry))
    for dest, source, entry in plan:
        if entry['before'] is None:
            if dest.exists():
                dest.unlink()
        else:
            atomic_write(dest, source.read_bytes(), entry['mode'])
    print('Restored:', backup)

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('target', type=Path, help='Installed @professional-wiki/mediawiki-mcp-server directory')
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--apply', '--anwenden', action='store_true')
    group.add_argument('--restore', type=Path, help='Restore a backup produced by this installer')
    args = parser.parse_args()
    if args.target.is_symlink():
        raise ValueError('Target must be a real directory')
    target = args.target.resolve(strict=True)
    if args.restore:
        restore(target, args.restore.resolve(strict=True))
        return
    meta = json.loads((target / 'package.json').read_text())
    if meta.get('name') != '@professional-wiki/mediawiki-mcp-server' or meta.get('version') != '0.21.0':
        raise ValueError('Requires official MCP 0.21.0. Install it separately before migrating from 0.20.x.')
    manifest = json.loads((ROOT / 'DATEIVERGLEICH.json').read_text())
    plan = []
    for name, entry in manifest.items():
        dest = safe_path(target, name)
        source = safe_path(ROOT / 'Austauschdateien', name)
        if digest(source) != entry['after']:
            raise ValueError('Patch checksum mismatch: ' + name)
        actual = digest(dest)
        if actual == entry['after']:
            continue
        if actual != entry['before']:
            raise ValueError('Unknown target change; no files changed: ' + name)
        plan.append((name, dest, source, entry))
    print('Checked:', len(plan), 'files to install')
    if not args.apply or not plan:
        print('No files changed.' if not plan else 'Checks only. Supply --apply to install.')
        return
    backup = target.parent / ('MCP-SWS-backup-' + datetime.now().strftime('%Y%m%d-%H%M%S-%f'))
    backup.mkdir(mode=0o700)
    entries = {}
    # Complete and verify the backup before changing any target file.
    for name, dest, source, entry in plan:
        mode = dest.stat().st_mode & 0o777 if dest.exists() else 0o644
        entries[name] = {**entry, 'mode': mode}
        if dest.exists():
            saved = backup / 'files' / name
            saved.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(dest, saved)
            if digest(saved) != entry['before']:
                raise ValueError('Backup verification failed: ' + name)
    (backup / 'BACKUP.json').write_text(json.dumps({'target': str(target), 'files': entries}, indent=2) + '\n')
    changed = []
    try:
        for name, dest, source, entry in plan:
            if digest(dest) != entry['before']:
                raise ValueError('Target changed during installation: ' + name)
            changed.append(name)
            atomic_write(dest, source.read_bytes(), entries[name]['mode'])
    except BaseException:
        for name in reversed(changed):
            dest = target / name
            entry = entries[name]
            if entry['before'] is None:
                if dest.exists():
                    dest.unlink()
            else:
                atomic_write(dest, (backup / 'files' / name).read_bytes(), entry['mode'])
        raise
    print('Installed SWS 0.5.0 patch. Backup:', backup)
    print('Configuration, credentials and package version unchanged. Restart the MCP connection.')

if __name__ == '__main__':
    try:
        main()
    except (ValueError, OSError, KeyError) as error:
        raise SystemExit(str(error))
