#!/usr/bin/env python3
"""Install SemanticWikiSchema 0.5.0 into an existing MediaWiki (first installation only)."""
from pathlib import Path
import argparse, copy, fnmatch, json, os, shutil, subprocess, sys, tempfile

VERSION = '0.5.0'
PACKAGE = 'aufeinwort/semantic-wikischema'
REPOSITORY = {'type': 'vcs', 'url': 'https://github.com/Klaus-Feldmann/SemanticWikiSchema'}
PREVIOUS = {PACKAGE, 'aufeinwort/semantic-neowiki', 'neowiki/neowiki'}


def read_json(path, default=None):
    if not path.exists() and default is not None:
        return copy.deepcopy(default)
    if path.is_symlink():
        raise RuntimeError('Configuration symlinks require manual installation: ' + str(path))
    value = json.loads(path.read_text(encoding='utf-8'))
    if not isinstance(value, dict):
        raise RuntimeError('Expected a JSON object: ' + str(path))
    return value


def prepare(root):
    root = root.resolve()
    for marker in ['maintenance/run.php', 'includes/DefaultSettings.php', 'LocalSettings.php', 'vendor/autoload.php']:
        if not (root / marker).is_file():
            raise RuntimeError('Not a configured MediaWiki with installed dependencies; missing ' + marker)
    for name in ['SemanticWikiSchema', 'SemanticNeoWiki', 'NeoWiki', 'SWS']:
        if os.path.lexists(root / 'extensions' / name):
            raise RuntimeError('First installation only; existing extension directory: ' + name + '. Contact info@aufeinwort.info.')
    main_path, local_path = root / 'composer.json', root / 'composer.local.json'
    main, local = read_json(main_path), read_json(local_path, {})
    for data in [main, local, read_json(root / 'composer.lock', {})]:
        names = set(data.get('require', {})) | set(data.get('require-dev', {}))
        names |= {p.get('name') for p in data.get('packages', []) + data.get('packages-dev', [])}
        if names & PREVIOUS:
            raise RuntimeError('Previous SWS/NeoWiki Composer installation detected. Contact info@aufeinwort.info.')
    includes = main.get('extra', {}).get('merge-plugin', {}).get('include', [])
    if isinstance(includes, str):
        includes = [includes]
    if not any(value in ['composer.local.json', './composer.local.json'] for value in includes):
        raise RuntimeError('MediaWiki composer.json must already merge composer.local.json; see installation prerequisites.')
    merged = copy.deepcopy(local)
    require = merged.setdefault('require', {})
    if not isinstance(require, dict):
        raise RuntimeError('composer.local.json require must be an object.')
    require[PACKAGE] = VERSION
    repositories = merged.setdefault('repositories', [])
    if isinstance(repositories, list):
        if REPOSITORY not in repositories:
            repositories.insert(0, copy.deepcopy(REPOSITORY))
    elif isinstance(repositories, dict):
        key = 'semantic-wikischema'
        if key in repositories and repositories[key] != REPOSITORY:
            raise RuntimeError('Existing semantic-wikischema repository differs; review manually.')
        merged['repositories'] = {key: copy.deepcopy(REPOSITORY), **repositories}
    else:
        raise RuntimeError('Unsupported composer.local.json repository policy; review manually.')
    updated_main = copy.deepcopy(main)
    config = updated_main.setdefault('config', {})
    plugins = config.setdefault('allow-plugins', {})
    if plugins is False:
        raise RuntimeError('Composer plugins are explicitly disabled; no settings changed.')
    if plugins is not True:
        if not isinstance(plugins, dict):
            raise RuntimeError('Unsupported Composer allow-plugins configuration.')
        for name in ['wikimedia/composer-merge-plugin', 'composer/installers']:
            policy = next((allowed for pattern, allowed in plugins.items() if fnmatch.fnmatchcase(name, pattern)), None)
            if policy is False:
                raise RuntimeError('Required Composer plugin explicitly disabled: ' + name)
            if policy is None:
                plugins[name] = True
        # Preserve explicit plugin policies, including matching wildcard policies.
        if not any(fnmatch.fnmatchcase('php-http/discovery', pattern) for pattern in plugins):
            plugins['php-http/discovery'] = False
    return root, {main_path: updated_main, local_path: merged}


def atomic_json(path, value):
    fd, temp = tempfile.mkstemp(prefix='.sws-install-', dir=path.parent)
    try:
        mode = path.stat().st_mode & 0o777 if path.exists() else 0o644
        with os.fdopen(fd, 'w', encoding='utf-8') as stream:
            json.dump(value, stream, ensure_ascii=False, indent=4)
            stream.write('\n')
        os.chmod(temp, mode)
        os.replace(temp, path)
    finally:
        if os.path.exists(temp):
            os.unlink(temp)


def apply(root, changes, php, composer, backup_root):
    backup_root = backup_root.expanduser().resolve()
    if backup_root == root or root in backup_root.parents:
        raise RuntimeError('Backup directory must be outside the MediaWiki web directory.')
    backup_root.mkdir(parents=True, exist_ok=True, mode=0o700)
    backup = Path(tempfile.mkdtemp(prefix='sws-0.5.0-', dir=backup_root))
    original = {}
    for name in ['composer.json', 'composer.local.json', 'composer.lock']:
        path = root / name
        original[name] = path.exists()
        if path.exists():
            shutil.copy2(path, backup / name)
            os.chmod(backup / name, 0o600)
    atomic_json(backup / 'original-files.json', original)
    print('Composer configuration backup:', backup, flush=True)
    for path, value in changes.items():
        atomic_json(path, value)
    args = [php, composer, 'update', PACKAGE, '--with-dependencies', '--no-dev', '--prefer-dist', '--no-scripts', '--no-interaction', '--no-progress']
    result = subprocess.run(args, cwd=root)
    if result.returncode:
        raise RuntimeError('Composer did not finish. Stop here; configuration and lock backups are in ' + str(backup))
    extension = root / 'extensions' / 'SemanticWikiSchema' / 'extension.json'
    if not extension.is_file() or read_json(extension).get('version') != VERSION:
        raise RuntimeError('Installed extension version/path differs from the requested release. Stop here.')
    print('SWS ' + VERSION + ' files and dependencies installed.', flush=True)
    print('Next: add the LocalSettings load lines, run update and rebuildLocalisationCache, then start/check the job runner.')
    print('Guide: https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.5.0/docs/operations/install/README.md')
    print('Deutsch: https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.5.0/docs/operations/install/de/README.md')
    return backup


def executable(value):
    found = shutil.which(value)
    if not found:
        raise RuntimeError('Executable not found: ' + value)
    return str(Path(found).resolve())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--mediawiki-root', required=True, type=Path)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--dry-run', action='store_true')
    group.add_argument('--apply', action='store_true')
    parser.add_argument('--php', default='php')
    parser.add_argument('--composer', default='composer')
    parser.add_argument('--backup-dir', type=Path, default=Path.home() / '.sws-installer-backups')
    args = parser.parse_args()
    root, changes = prepare(args.mediawiki_root)
    php, composer = executable(args.php), executable(args.composer)
    if subprocess.run([php, '-r', 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);']).returncode:
        raise RuntimeError('PHP 8.3 or newer is required.')
    print('First-installation target:', root)
    print('Composer package:', PACKAGE + ':' + VERSION)
    print('Changed files: composer.json (plugin permissions), composer.local.json (SWS requirement/repository).')
    if not args.apply:
        print('Preview only. No configuration files, packages, LocalSettings or wiki data were changed.')
        return
    apply(root, changes, php, composer, args.backup_dir)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError) as error:
        print('STOP:', error, file=sys.stderr)
        sys.exit(1)
