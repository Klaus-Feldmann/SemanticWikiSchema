#!/usr/bin/env python3
"""Build a local, unpublished extension-folder ZIP; never contact GitHub or Composer."""
import argparse,hashlib,json,zipfile
from datetime import datetime
from pathlib import Path
IGNORE={'.git','node_modules','vendor','__pycache__','.pytest_cache','.vite'}
def sources(root):
    result={}
    for p in sorted(root.rglob('*')):
        rel=p.relative_to(root)
        if any(x in IGNORE for x in rel.parts) or p.name=='.DS_Store' or p.suffix in {'.pyc','.tsbuildinfo'}:continue
        if p.is_symlink():raise ValueError('Unexpected symlink: '+str(rel))
        if p.is_file():result[rel.as_posix()]=p.read_bytes()
    return result

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',required=True,type=Path);a=parser.parse_args()
    root=Path(__file__).resolve().parents[1];out=a.output.resolve()
    if out==root or root in out.parents:raise ValueError('Output must be outside source directory.')
    metadata=json.loads((root/'SEMANTICWIKISCHEMA-VERSION.json').read_text());version=json.loads((root/'extension.json').read_text())['version']
    if metadata.get('publicationAllowed') is not False or version!=metadata['extensionVersion']:raise ValueError('Expected consistent private-candidate metadata.')
    entries=sources(root);manifest='publication/DISTRIBUTION-SHA256SUMS'
    entries[manifest]=(''.join(hashlib.sha256(data).hexdigest()+'  '+name+'\n'for name,data in sorted(entries.items())if name!=manifest)).encode()
    (root/manifest).write_bytes(entries[manifest]);out.mkdir(parents=True,exist_ok=True)
    archive=out/f'SemanticWikiSchema-{version}-test.zip'
    # MediaWiki ExtensionRegistry varies its cache by extension.json filemtime.
    # Preserve actual source mtimes instead of assigning every release the same timestamp.
    latest=max((root/name).stat().st_mtime for name in entries if name!=manifest)
    with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED)as z:
        for name,data in sorted(entries.items()):
            timestamp=latest if name==manifest else (root/name).stat().st_mtime
            date_time=datetime.fromtimestamp(max(315532800,min(timestamp,4354819198))).timetuple()[:6]
            info=zipfile.ZipInfo('SemanticWikiSchema/'+name,date_time=date_time);info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;z.writestr(info,data)
    with zipfile.ZipFile(archive)as z:
        if z.testzip()is not None:raise ValueError('ZIP CRC failure')
        names=set(z.namelist());listed=set()
        for line in z.read('SemanticWikiSchema/'+manifest).decode().splitlines():
            digest,name=line.split('  ',1);listed.add('SemanticWikiSchema/'+name)
            if hashlib.sha256(z.read('SemanticWikiSchema/'+name)).hexdigest()!=digest:raise ValueError('Hash mismatch: '+name)
        if names!=listed|{'SemanticWikiSchema/'+manifest}:raise ValueError('Manifest coverage failure')
    (out/'SHA256SUMS').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n')
    result={'version':version,'files':len(entries),'archive':archive.name,'allFileHashesVerified':True,'publication':False,'liveMediaWikiTest':'pending'}
    (out/'PACKAGE-VERIFICATION.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))
if __name__=='__main__':main()
