import contextlib
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import zipfile

spec = importlib.util.spec_from_file_location('sws_upgrade', Path(__file__).resolve().parents[1] / 'upgrade.py')
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)


class UpgradeTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.base = Path(self.temp.name)
        self.wiki = self.base / 'wiki'
        self.target = self.wiki / 'extensions/SemanticWikiSchema'
        self.target.mkdir(parents=True)
        (self.wiki / 'LocalSettings.php').write_text('keep local settings')
        (self.wiki / 'maintenance').mkdir()
        (self.wiki / 'maintenance/run.php').write_text('core')
        self.write('extension.json', json.dumps({'version': '0.5.02'}))
        self.write('src/Old.php', '<?php old')
        self.write('vendor/autoload.php', '<?php dependencies')
        self.write('graph-explorer/config.js', 'local endpoint')
        self.write('my-notes.txt', 'keep local notes')
        self.before = m.inventory(self.target)
        self.profile = {'name': 'test-0.5.02', 'checked': {n: self.before[n] for n in ['extension.json', 'src/Old.php']}, 'owned': ['extension.json', 'src/Old.php', 'graph-explorer/config.js']}
        p = patch.object(m, 'profiles', return_value=[self.profile])
        p.start()
        self.addCleanup(p.stop)
        self.archive = self.base / 'source.zip'
        self.payload = {'extension.json': json.dumps({'version': '0.6.0'}).encode(), 'src/New.php': b'<?php new', 'graph-explorer/config.js': b'default endpoint'}
        self.make_archive()
        self.backups = self.base / 'private-backups'

    def write(self, name, data):
        p = self.target / name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(data)

    def make_archive(self, extra=None):
        entries = dict(self.payload)
        entries['publication/DISTRIBUTION-SHA256SUMS'] = ''.join(hashlib.sha256(data).hexdigest() + '  ' + name + '\n' for name, data in sorted(entries.items())).encode()
        with zipfile.ZipFile(self.archive, 'w') as z:
            for name, data in entries.items():
                z.writestr('SemanticWikiSchema/' + name, data)
            if extra:
                z.writestr(*extra)

    def apply(self):
        with contextlib.redirect_stdout(io.StringIO()):
            return m.apply(self.target, self.archive, self.backups)

    def test_preview_does_not_mutate_anything(self):
        m.wiki_root(self.target)
        m.match_profile(self.target)
        m.read_payload(self.archive)
        self.assertEqual(m.inventory(self.target), self.before)
        self.assertFalse(self.backups.exists())

    def test_apply_retains_dependencies_configuration_and_local_files(self):
        backup = self.apply()
        self.assertEqual((self.target / 'vendor/autoload.php').read_text(), '<?php dependencies')
        self.assertEqual((self.target / 'graph-explorer/config.js').read_text(), 'local endpoint')
        self.assertEqual((self.target / 'my-notes.txt').read_text(), 'keep local notes')
        self.assertEqual((self.wiki / 'LocalSettings.php').read_text(), 'keep local settings')
        self.assertFalse((self.target / 'src/Old.php').exists())
        self.assertTrue((self.target / 'src/New.php').exists())
        self.assertEqual(m.inventory(backup / 'original'), self.before)

    def test_complete_byte_identical_rollback(self):
        backup = self.apply()
        m.rollback(self.target, backup, False)
        self.assertNotEqual(m.inventory(self.target), self.before)
        m.rollback(self.target, backup, True)
        self.assertEqual(m.inventory(self.target), self.before)

    def test_drift_in_old_code_is_refused(self):
        self.write('src/Old.php', 'local modifications')
        with self.assertRaisesRegex(RuntimeError, 'Unknown or modified'):
            self.apply()
        self.assertFalse(self.backups.exists())

    def test_new_code_modified_after_upgrade_refuses_rollback(self):
        backup = self.apply()
        self.write('src/New.php', 'changed after install')
        with self.assertRaisesRegex(RuntimeError, 'changed'):
            m.rollback(self.target, backup, True)

    def test_damaged_backup_refuses_rollback(self):
        backup = self.apply()
        (backup / 'original/src/Old.php').write_text('broken')
        with self.assertRaisesRegex(RuntimeError, 'changed'):
            m.rollback(self.target, backup, True)

    def test_backup_under_web_root_refused(self):
        self.backups = self.wiki / 'backups'
        with self.assertRaisesRegex(RuntimeError, 'outside'):
            self.apply()
        self.assertEqual(m.inventory(self.target), self.before)

    def test_composer_managed_installation_refused(self):
        (self.wiki / 'composer.lock').write_text(json.dumps({'packages': [{'name': 'aufeinwort/semantic-wikischema'}]}))
        with self.assertRaisesRegex(RuntimeError, 'Composer-managed'):
            self.apply()

    def test_symlink_in_runtime_refused(self):
        (self.target / 'src/Link.php').symlink_to(self.target / 'src/Old.php')
        with self.assertRaisesRegex(RuntimeError, 'Symbolic link'):
            self.apply()

    def test_archive_traversal_refused(self):
        self.make_archive(('../outside.php', 'bad'))
        with self.assertRaisesRegex(RuntimeError, 'Unsafe'):
            self.apply()
        self.assertFalse((self.base / 'outside.php').exists())

    def test_unlisted_payload_refused(self):
        self.make_archive(('SemanticWikiSchema/extra.php', 'bad'))
        with self.assertRaisesRegex(RuntimeError, 'Incomplete'):
            self.apply()

    def test_duplicate_payload_refused(self):
        self.make_archive(('SemanticWikiSchema/src/New.php', 'bad'))
        with self.assertRaisesRegex(RuntimeError, 'Duplicate'):
            self.apply()

    def test_wrong_version_refused(self):
        self.payload['extension.json'] = b'{"version":"0.5.0"}'
        self.make_archive()
        with self.assertRaisesRegex(RuntimeError, 'Wrong source'):
            self.apply()

    def test_swap_failure_restores_original(self):
        original_rename = Path.rename
        def fail_stage(path, target):
            if path.name.startswith('.sws-upgrade-'):
                raise OSError('simulated directory switch failure')
            return original_rename(path, target)
        with patch.object(Path, 'rename', fail_stage):
            with self.assertRaises(OSError):
                self.apply()
        self.assertEqual(m.inventory(self.target), self.before)

    def test_concurrent_edit_prevents_swap(self):
        original_copy = m.shutil.copytree
        def edit_during_copy(src, dst, *args, **kwargs):
            result = original_copy(src, dst, *args, **kwargs)
            if Path(dst).name.startswith('.sws-upgrade-'):
                self.write('src/Old.php', 'concurrent edit')
            return result
        with patch.object(m.shutil, 'copytree', edit_during_copy):
            with self.assertRaisesRegex(RuntimeError, 'changed during'):
                self.apply()
        self.assertEqual((self.target / 'src/Old.php').read_text(), 'concurrent edit')


if __name__ == '__main__':
    unittest.main()
