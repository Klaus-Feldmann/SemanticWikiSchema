import json,re,unittest
from pathlib import Path
class FrontendMessagesTest(unittest.TestCase):
 def test_literal_frontend_messages_are_registered(self):
  root=Path(__file__).resolve().parents[2]
  catalogue=json.loads((root/'i18n/en.json').read_text())
  registered=set(json.loads((root/'extension.json').read_text())['ResourceModules']['ext.sws']['messages'])
  used=set()
  for p in (root/'resources/ext.sws/src').rglob('*'):
   if p.suffix in ['.ts','.vue']:
    used.update(re.findall(r"(?:\$i18n|mw\.message|mw\.msg)\(\s*['\"](sws-[^'\"]+)",p.read_text()))
  # Dynamic message families have their own registrations; test complete literal keys here.
  self.assertEqual(sorted((used & catalogue.keys())-registered),[])
if __name__=='__main__':unittest.main()
