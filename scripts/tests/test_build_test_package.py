import importlib.util,json,os,subprocess,tempfile,unittest,zipfile
from pathlib import Path
class PackageTimestampTest(unittest.TestCase):
 def test_manifest_timestamp_changes_between_deployments(self):
  with tempfile.TemporaryDirectory() as t:
   root=Path(t)/'source';(root/'scripts').mkdir(parents=True);(root/'publication').mkdir()
   script=Path(__file__).resolve().parents[1]/'build_test_package.py'
   (root/'scripts/build_test_package.py').write_bytes(script.read_bytes())
   (root/'extension.json').write_text(json.dumps({'version':'0.5.00-alpha.7'}))
   (root/'SEMANTICWIKISCHEMA-VERSION.json').write_text(json.dumps({'publicationAllowed':False,'extensionVersion':'0.5.00-alpha.7'}))
   first=1794528000;second=first+120
   dates=[]
   for stamp in [first,second]:
    os.utime(root/'extension.json',(stamp,stamp))
    out=Path(t)/str(stamp)
    subprocess.run(['python3',str(root/'scripts/build_test_package.py'),'--output',str(out)],check=True,capture_output=True)
    with zipfile.ZipFile(next(out.glob('*.zip'))) as z:
     dates.append(z.getinfo('SemanticWikiSchema/extension.json').date_time)
   self.assertNotEqual(dates[0],dates[1])
   self.assertEqual(dates[0][:3],(2026,11,13))
if __name__=='__main__':unittest.main()
