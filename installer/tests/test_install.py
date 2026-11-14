import sys
sys.dont_write_bytecode=True
from pathlib import Path
import importlib.util,json,os,tempfile,unittest
from unittest.mock import patch
from types import SimpleNamespace
spec=importlib.util.spec_from_file_location('sws_install',Path(__file__).resolve().parents[1]/'install.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)

class InstallerTests(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.addCleanup(self.temp.cleanup);self.base=Path(self.temp.name);self.root=self.base/'wiki';self.root.mkdir()
  for name in ['maintenance/run.php','includes/DefaultSettings.php','LocalSettings.php','vendor/autoload.php']:
   p=self.root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text('<?php /* existing wiki */')
  self.main={'name':'local/wiki','require':{'wikimedia/composer-merge-plugin':'^2','other/existing':'^1'},'extra':{'merge-plugin':{'include':['composer.local.json'],'recurse':True}},'config':{'allow-plugins':{'wikimedia/composer-merge-plugin':True,'other/plugin':True}}}
  self.local={'require':{'other/extension':'^2'},'extra':{'keep':'unchanged'}}
  self.write('composer.json',self.main);self.write('composer.local.json',self.local);self.write('composer.lock',{'packages':[],'content-hash':'original'})
 def write(self,name,data):(self.root/name).write_text(json.dumps(data))
 def snap(self):return {str(p.relative_to(self.root)):p.read_bytes()for p in self.root.rglob('*')if p.is_file()}
 def test_release_apply_uses_the_verified_first_installation_path(self):
  before=self.snap()
  with patch.object(sys,'argv',['install.py','--mediawiki-root',str(self.root),'--apply']),patch.object(m,'executable',return_value='/bin/true'),patch.object(m.subprocess,'run',return_value=SimpleNamespace(returncode=0)),patch.object(m,'apply')as apply:
   m.main()
   apply.assert_called_once()
   self.assertEqual(apply.call_args.args[0],self.root.resolve())
  self.assertEqual(self.snap(),before)
 def test_preview_preserves_unrelated_settings_and_writes_nothing(self):
  before=self.snap();root,changes=m.prepare(self.root)
  self.assertEqual(self.snap(),before);self.assertEqual(changes[root/'composer.local.json']['require']['other/extension'],'^2');self.assertEqual(changes[root/'composer.local.json']['require'][m.PACKAGE],m.VERSION);self.assertTrue(changes[root/'composer.json']['config']['allow-plugins']['other/plugin'])
 def test_old_directories_are_not_overwritten(self):
  for name in ['NeoWiki','SemanticWikiSchema','SemanticNeoWiki','SWS']:
   p=self.root/'extensions'/name;p.mkdir(parents=True)
   with self.assertRaises(RuntimeError):m.prepare(self.root)
   p.rmdir()
 def test_locked_old_package_refused(self):
  self.write('composer.lock',{'packages':[{'name':'neowiki/neowiki'}]})
  with self.assertRaises(RuntimeError):m.prepare(self.root)
 def test_existing_requirements_refused(self):
  self.local['require'][m.PACKAGE]='0.1.22';self.write('composer.local.json',self.local)
  with self.assertRaises(RuntimeError):m.prepare(self.root)
 def test_plugin_denial_is_not_overridden(self):
  for policy in [False,{'*':False},{'wikimedia/*':False}]:
   self.main['config']['allow-plugins']=policy;self.write('composer.json',self.main)
   with self.assertRaises(RuntimeError):m.prepare(self.root)
 def test_named_repositories_preserved(self):
  self.local['repositories']={'private':{'type':'composer','url':'https://packages.example.test'},'packagist.org':False};self.write('composer.local.json',self.local)
  root,changes=m.prepare(self.root);repos=changes[root/'composer.local.json']['repositories'];self.assertEqual(repos['private'],self.local['repositories']['private']);self.assertFalse(repos['packagist.org'])
 def test_existing_plugin_permissions_preserved(self):
  self.main['config']['allow-plugins']['php-http/discovery']=True;self.write('composer.json',self.main)
  root,changes=m.prepare(self.root);self.assertTrue(changes[root/'composer.json']['config']['allow-plugins']['php-http/discovery'])
 def test_backups_cannot_be_created_under_web_root(self):
  root,changes=m.prepare(self.root);before=self.snap()
  with self.assertRaises(RuntimeError):m.apply(root,changes,'php','composer',root/'backups')
  self.assertEqual(before,self.snap())
 def test_apply_backs_up_and_keeps_localsettings(self):
  before=self.snap();root,changes=m.prepare(self.root)
  def install(args,cwd):
   self.assertEqual(args[:4],['php','composer','update',m.PACKAGE]);self.assertEqual(cwd,root)
   target=root/'extensions/SemanticWikiSchema/extension.json';target.parent.mkdir(parents=True);target.write_text(json.dumps({'version':m.VERSION}));return SimpleNamespace(returncode=0)
  with patch.object(m.subprocess,'run',side_effect=install):backup=m.apply(root,changes,'php','composer',self.base/'private')
  self.assertEqual((backup/'composer.json').read_bytes(),before['composer.json']);self.assertEqual((backup/'composer.lock').read_bytes(),before['composer.lock']);self.assertEqual((root/'LocalSettings.php').read_bytes(),before['LocalSettings.php']);self.assertEqual(backup.stat().st_mode&0o777,0o700)
 def test_composer_failure_is_reported_with_backup(self):
  root,changes=m.prepare(self.root)
  with patch.object(m.subprocess,'run',return_value=SimpleNamespace(returncode=2)):
   with self.assertRaisesRegex(RuntimeError,'Composer did not finish'):m.apply(root,changes,'php','composer',self.base/'private')
  self.assertEqual(len(list((self.base/'private').glob('*/composer.lock'))),1)
 def test_symlink_configuration_refused(self):
  target=self.base/'config.json';(self.root/'composer.local.json').rename(target);(self.root/'composer.local.json').symlink_to(target)
  with self.assertRaises(RuntimeError):m.prepare(self.root)
 def test_missing_core_merge_setup_refused(self):
  self.main.pop('extra');self.write('composer.json',self.main)
  with self.assertRaises(RuntimeError):m.prepare(self.root)

if __name__=='__main__':unittest.main()
