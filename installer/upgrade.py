#!/usr/bin/env python3
"""Checked, reversible folder upgrade from known SWS 0.6.0 distributions to 0.7.0.2."""
from pathlib import Path, PurePosixPath
import argparse, datetime, hashlib, json, os, shutil, stat, tempfile, zipfile
VERSION = '0.7.0.2'
ROOT = Path(__file__).resolve().parent
PRESERVE = {'graph-explorer/config.js'}
SKIP = {'.git', 'node_modules', '__pycache__', '.DS_Store', '.phpunit.result.cache'}


def inventory(root):
    result = {}
    for path in sorted(root.rglob('*')):
        rel = path.relative_to(root)
        if any(part in SKIP for part in rel.parts):
            continue
        if path.is_symlink():
            raise RuntimeError('Symbolic link requires manual upgrade: ' + str(path))
        if path.is_file():
            result[rel.as_posix()] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


def profiles():
    return json.loads((ROOT / 'upgrade-profiles.json').read_text())['profiles']


def match_profile(target):
    actual = inventory(target)
    for profile in profiles():
        if all(actual.get(name) == digest for name, digest in profile['checked'].items()):
            legacy = target / 'integrations/mcp'
            if legacy.exists():
                owned = set(profile['owned'])
                extra = [path for path in actual if path.startswith('integrations/mcp/') and path not in owned]
                if extra:
                    raise RuntimeError('Additional files in the former MCP integration require manual review')
            return profile, actual
    raise RuntimeError('Unknown or modified 0.6.0 code. No files changed; review manually.')


def read_payload(archive):
    entries = {}
    with zipfile.ZipFile(archive) as source:
        for info in source.infolist():
            path = PurePosixPath(info.filename)
            if path.is_absolute() or '..' in path.parts or not path.parts or path.parts[0] != 'SemanticWikiSchema':
                raise RuntimeError('Unsafe source archive path')
            if stat.S_ISLNK(info.external_attr >> 16):
                raise RuntimeError('Source archive contains symbolic links')
            if info.is_dir():
                continue
            name = '/'.join(path.parts[1:])
            if not name or name in entries:
                raise RuntimeError('Duplicate or empty source archive path')
            entries[name] = source.read(info)
    manifest = entries.get('publication/DISTRIBUTION-SHA256SUMS', b'').decode().splitlines()
    listed = {}
    for line in manifest:
        digest, name = line.split('  ', 1)
        if name in listed:
            raise RuntimeError('Duplicate manifest entry')
        listed[name] = digest
    if set(listed) != set(entries) - {'publication/DISTRIBUTION-SHA256SUMS'}:
        raise RuntimeError('Incomplete source archive manifest')
    if any(hashlib.sha256(entries[name]).hexdigest() != digest for name, digest in listed.items()):
        raise RuntimeError('Source archive checksum mismatch')
    if json.loads(entries['extension.json'])['version'] != VERSION:
        raise RuntimeError('Wrong source archive version')
    return entries


def wiki_root(target):
    if target.parent.name != 'extensions':
        raise RuntimeError('Expected MediaWiki extensions/SemanticWikiSchema directory')
    wiki = target.parent.parent
    if not (wiki / 'LocalSettings.php').is_file() or not (wiki / 'maintenance/run.php').is_file():
        raise RuntimeError('MediaWiki layout not found')
    lock = wiki / 'composer.lock'
    if lock.exists():
        data = json.loads(lock.read_text())
        if any(p.get('name') == 'aufeinwort/semantic-wikischema' for p in data.get('packages', []) + data.get('packages-dev', [])):
            raise RuntimeError('Composer-managed SWS: use the Composer upgrade in the guide; do not replace managed files.')
    return wiki


def swap(target, prepared):
    retired = Path(tempfile.mkdtemp(prefix='.sws-retired-', dir=target.parent))
    retired.rmdir()
    target.rename(retired)
    try:
        prepared.rename(target)
    except BaseException:
        retired.rename(target)
        raise
    shutil.rmtree(retired)


def apply(target, archive, backups):
    if target.is_symlink():
        raise RuntimeError('Extension symlink requires manual upgrade')
    wiki = wiki_root(target).resolve()
    target = target.resolve()
    backups = backups.resolve()
    if backups == wiki or wiki in backups.parents:
        raise RuntimeError('Backups must be outside the MediaWiki web root')
    if target.is_symlink():
        raise RuntimeError('Extension symlink requires manual upgrade')
    profile, before = match_profile(target)
    entries = read_payload(archive)
    backups.mkdir(parents=True, exist_ok=True, mode=0o700)
    backup = Path(tempfile.mkdtemp(prefix='sws-0.7.0.2-', dir=backups))
    original = backup / 'original'
    shutil.copytree(target, original, symlinks=True)
    if inventory(original) != before:
        raise RuntimeError('Backup verification failed; no installed files changed')
    stage = Path(tempfile.mkdtemp(prefix='.sws-upgrade-', dir=target.parent))
    try:
        shutil.copytree(target, stage, dirs_exist_ok=True, symlinks=True)
        for name in profile['owned']:
            old = stage / name
            if name not in entries and name not in PRESERVE and old.is_file():
                old.unlink()
        legacy = stage / 'integrations/mcp'
        if legacy.is_dir():
            # Only known files were removed. Refuse to discard any remaining local content.
            for path in sorted(legacy.rglob('*'), key=lambda path: len(path.parts), reverse=True):
                if path.is_dir() and not path.is_symlink():
                    path.rmdir()
            legacy.rmdir()
        for name, data in entries.items():
            path = stage / name
            if name in PRESERVE and path.exists():
                continue
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(data)
        after = inventory(stage)
        journal = {'version': VERSION, 'target': str(target), 'profile': profile['name'], 'before': before, 'after': after}
        (backup / 'journal.json').write_text(json.dumps(journal, indent=2) + '\n')
        # Reject concurrent edits before switching directories.
        if inventory(target) != before:
            raise RuntimeError('Installed files changed during preparation; no swap performed')
        swap(target, stage)
        if inventory(target) != after:
            raise RuntimeError('Post-installation verification failed; stop and restore backup')
    finally:
        if stage.exists():
            shutil.rmtree(stage)
    print('Upgraded to ' + VERSION + '. Backup:', backup)
    print('Run the maintenance commands from the upgrade guide before restarting web PHP and persistent job processes.')
    return backup


def rollback(target, backup, apply_changes):
    wiki_root(target)
    journal = json.loads((backup / 'journal.json').read_text())
    if str(target.resolve()) != journal['target']:
        raise RuntimeError('Backup belongs to another target')
    if inventory(target) != journal['after'] or inventory(backup / 'original') != journal['before']:
        raise RuntimeError('Installed files or backup changed; automatic rollback refused')
    if not apply_changes:
        print('Rollback verified; nothing changed')
        return
    stage = Path(tempfile.mkdtemp(prefix='.sws-restore-', dir=target.parent))
    try:
        shutil.copytree(backup / 'original', stage, dirs_exist_ok=True, symlinks=True)
        swap(target, stage)
    finally:
        if stage.exists():
            shutil.rmtree(stage)
    if inventory(target) != journal['before']:
        raise RuntimeError('Restored files differ from backup')
    print('Previous extension folder restored. Restart PHP/job processes.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--extension', type=Path, required=True)
    parser.add_argument('--source', type=Path, default=ROOT / 'SemanticWikiSchema-0.7.0.2-source.zip')
    parser.add_argument('--backup-dir', type=Path)
    parser.add_argument('--rollback', type=Path)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--processes-stopped', action='store_true')
    args = parser.parse_args()
    target = args.extension.absolute()
    if target.is_symlink():
        raise RuntimeError('Extension symlink requires manual upgrade')
    wiki_root(target)
    if args.apply and not args.processes_stopped:
        raise RuntimeError('Stop PHP and persistent job processes, then pass --processes-stopped')
    if args.rollback:
        rollback(target, args.rollback, args.apply)
        return
    version = json.loads((target / 'extension.json').read_text())['version']
    if version == VERSION:
        desired = read_payload(args.source)
        current = inventory(target)
        if any(name not in PRESERVE and current.get(name) != hashlib.sha256(data).hexdigest() for name, data in desired.items()):
            raise RuntimeError('Version is 0.7.0.2 but files differ; no changes made')
        print('Exact 0.7.0.2 release already installed; nothing changed')
        return
    if version != '0.6.0':
        raise RuntimeError('Only known SWS 0.6.0 distributions are supported')
    profile, _ = match_profile(target)
    read_payload(args.source)
    if not args.apply:
        print('Verified upgrade preview:', profile['name'], '->', VERSION, '; nothing changed')
        return
    if not args.backup_dir:
        raise RuntimeError('--backup-dir outside the web root is required')
    apply(target, args.source, args.backup_dir)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyError, zipfile.BadZipFile) as error:
        raise SystemExit('STOP: ' + str(error))
