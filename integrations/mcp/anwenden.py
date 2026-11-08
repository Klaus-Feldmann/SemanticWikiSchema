#!/usr/bin/env python3
"""Checked incremental runtime patch for MediaWiki-MCP 0.20.0/0.20.1; dry-run by default."""
from pathlib import Path
from datetime import datetime
import argparse,hashlib,json,os,shutil,tempfile,stat
r=Path(__file__).resolve().parent
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('ziel',type=Path,help='Installed @professional-wiki/mediawiki-mcp-server directory')
parser.add_argument('--anwenden',action='store_true')
a=parser.parse_args();target=a.ziel.resolve()
meta=json.loads((target/'package.json').read_text())
if meta.get('name')!='@professional-wiki/mediawiki-mcp-server' or meta.get('version') not in ['0.20.0','0.20.1']:
 raise SystemExit('Unbekannte Ausgangsversion. Keine Änderung.')
manifest=json.loads((r/'DATEIVERGLEICH.json').read_text());work=[]
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None
for name,entry in manifest.items():
 path=Path(name);dest=target/path;source=r/'Austauschdateien'/path
 if path.is_absolute() or '..' in path.parts or dest.is_symlink() or not dest.resolve().is_relative_to(target):raise SystemExit('Unzulässiger Zielpfad.')
 if digest(source)!=entry['after']:raise SystemExit('Paket-Prüfsumme abweichend: '+name)
 actual=digest(dest)
 if actual==entry['after']:continue
 if actual not in entry.get('acceptedBefore',[entry['before']]):raise SystemExit('Abweichender Ausgangsstand, keine Änderung: '+name)
 work.append(name)
if not work:print('Datenansicht-Patch bereits installiert. Keine Änderung.');raise SystemExit(0)
print('Ausgang und Patch geprüft. Betroffene Dateien:',len(work))
if not a.anwenden:print('Nur geprüft. Zum Anwenden --anwenden ergänzen.');raise SystemExit(0)
backup=target.parent/('MediaWiki-MCP-Datenansicht-Sicherung-'+datetime.now().strftime('%Y%m%d-%H%M%S-%f'));backup.mkdir()
(backup/'VORGANG.json').write_text(json.dumps({'target':str(target),'files':work,'previousVersion':meta['version']},indent=2))
for name in work:
 dest=target/name;dest.parent.mkdir(parents=True,exist_ok=True);before=dest.stat() if dest.exists() else None
 if before:
  saved=backup/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(dest,saved)
 fd,tmp=tempfile.mkstemp(prefix='.mcp-dataview-',dir=dest.parent)
 try:
  with os.fdopen(fd,'wb') as stream:stream.write((r/'Austauschdateien'/name).read_bytes())
  os.chmod(tmp,stat.S_IMODE(before.st_mode) if before else 0o644)
  os.replace(tmp,dest)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
print('Datenansicht-Patch angewendet. Sicherung:',backup)
print('Privatwiki-Anpassung, Konfiguration, OAuth und Versionsdateien unverändert. Nun MCP-Verbindung neu starten.')
