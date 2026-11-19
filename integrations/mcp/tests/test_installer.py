import contextlib
import hashlib
import importlib.util
import io
import json
import tempfile
import unittest
from pathlib import Path
from unittest import mock

SCRIPT = Path(__file__).resolve().parents[1] / 'patch_core.py'
spec = importlib.util.spec_from_file_location('installer', SCRIPT)
installer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(installer)


def sha(text):
    return hashlib.sha256(text.encode()).hexdigest()


class InstallerTests(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        self.package = self.root / 'patch'
        self.target = self.root / 'target'
        self.backups = self.root / 'backups'
        self.target.mkdir()
        (self.target / 'dist').mkdir()
        (self.target / 'dist/server.js').write_text('old')
        (self.target / 'package.json').write_text('unchanged')
        (self.target / 'config.json').write_text('PRIVATE-CONFIG-UNCHANGED')
        (self.target / 'dist/server.js').chmod(0o755)
        (self.package / 'payload/dist').mkdir(parents=True)
        (self.package / 'payload/agent-help').mkdir()
        (self.package / 'payload/dist/server.js').write_text('new')
        (self.package / 'payload/agent-help/overview.md').write_text('help')
        self.manifest = {
            'patchId': 'test',
            'baseline': {'dist/server.js': sha('old'), 'package.json': sha('unchanged')},
            'changes': {
                'dist/server.js': {'before': sha('old'), 'after': sha('new')},
                'agent-help/overview.md': {'before': None, 'after': sha('help')},
            },
        }
        self.write_manifest()
        patcher = mock.patch.object(installer, 'ROOT', self.package)
        patcher.start()
        self.addCleanup(patcher.stop)

    def write_manifest(self):
        (self.package / 'manifest.json').write_text(json.dumps(self.manifest))

    def install(self):
        with contextlib.redirect_stdout(io.StringIO()):
            installer.install(self.target, installer.load_manifest(), self.backups)
        return next(self.backups.iterdir())

    def test_dry_run_is_read_only(self):
        self.assertEqual(installer.state(self.target, installer.load_manifest()), 'base')
        self.assertFalse(self.backups.exists())
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'old')

    def test_install_and_complete_rollback(self):
        backup = self.install()
        self.assertEqual(installer.state(self.target, self.manifest), 'installed')
        with contextlib.redirect_stdout(io.StringIO()):
            installer.rollback(self.target, backup)
        self.assertEqual(installer.state(self.target, self.manifest), 'base')
        self.assertFalse((self.target / 'agent-help/overview.md').exists())
        self.assertEqual((self.target / 'dist/server.js').stat().st_mode & 0o777, 0o755)

    def test_repeated_install_is_idempotent(self):
        self.install()
        with contextlib.redirect_stdout(io.StringIO()):
            installer.install(self.target, self.manifest, self.backups)
        self.assertEqual(len(list(self.backups.iterdir())), 1)

    def test_unknown_base_change_is_refused(self):
        (self.target / 'package.json').write_text('unexpected')
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'old')
        self.assertFalse(self.backups.exists())

    def test_missing_base_is_refused(self):
        (self.target / 'package.json').unlink()
        with self.assertRaises(ValueError):
            self.install()

    def test_existing_new_file_is_not_overwritten(self):
        (self.target / 'agent-help').mkdir()
        (self.target / 'agent-help/overview.md').write_text('owner content')
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual((self.target / 'agent-help/overview.md').read_text(), 'owner content')

    def test_payload_tampering_is_refused_before_writes(self):
        (self.package / 'payload/dist/server.js').write_text('tampered')
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'old')

    def test_rollback_refuses_later_target_changes(self):
        backup = self.install()
        (self.target / 'dist/server.js').write_text('later update')
        with self.assertRaises(ValueError):
            installer.rollback(self.target, backup)
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'later update')

    def test_rollback_refuses_corrupted_backup(self):
        backup = self.install()
        (backup / 'original/dist/server.js').write_text('broken')
        with self.assertRaises(ValueError):
            installer.rollback(self.target, backup)
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'new')

    def test_rollback_dry_run_leaves_patch_installed(self):
        backup = self.install()
        with contextlib.redirect_stdout(io.StringIO()):
            installer.rollback(self.target, backup, dry_run=True)
        self.assertEqual(installer.state(self.target, self.manifest), 'installed')

    def test_symlink_target_file_is_refused(self):
        (self.target / 'dist/server.js').unlink()
        outside = self.root / 'outside.js'
        outside.write_text('old')
        (self.target / 'dist/server.js').symlink_to(outside)
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual(outside.read_text(), 'old')

    def test_symlink_parent_directory_is_refused(self):
        outside = self.root / 'outside'
        outside.mkdir()
        (self.target / 'agent-help').symlink_to(outside, target_is_directory=True)
        with self.assertRaises(ValueError):
            self.install()
        self.assertEqual(list(outside.iterdir()), [])

    def test_path_traversal_is_refused(self):
        with self.assertRaises(ValueError):
            installer.safe_path(self.target, '../outside')
        with self.assertRaises(ValueError):
            installer.safe_path(self.target, '/outside')

    def test_private_config_is_untouched(self):
        backup = self.install()
        installer.rollback(self.target, backup)
        self.assertEqual((self.target / 'config.json').read_text(), 'PRIVATE-CONFIG-UNCHANGED')
        self.assertFalse((backup / 'original/config.json').exists())

    def test_failed_install_restores_already_written_files(self):
        original = installer.atomic_copy
        def fail_second(source, target, mode=None):
            if str(source).endswith('overview.md'):
                raise OSError('simulated disk failure')
            original(source, target, mode)
        with mock.patch.object(installer, 'atomic_copy', side_effect=fail_second):
            with self.assertRaises(OSError):
                self.install()
        self.assertEqual((self.target / 'dist/server.js').read_text(), 'old')
        self.assertFalse((self.target / 'agent-help/overview.md').exists())


if __name__ == '__main__':
    unittest.main()
