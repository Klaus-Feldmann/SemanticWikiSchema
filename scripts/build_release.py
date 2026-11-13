#!/usr/bin/env python3
"""Build and verify SWS release archives from a committed Git ref, never from untracked local files."""
from pathlib import Path, PurePosixPath
import argparse, hashlib, io, json, re, stat, subprocess, zipfile


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output',required=True,type=Path);parser.add_argument('--ref',default='HEAD');args=parser.parse_args()
    root=Path(__file__).resolve().parents[1];output=args.output.resolve();output.mkdir(parents=True,exist_ok=True)
    def git(*cmd):return subprocess.check_output(['git',*cmd],cwd=root)
    ref=git('rev-parse','--verify',args.ref+'^{commit}').decode().strip()
    manifest=json.loads(git('show',ref+':extension.json'));version=manifest['version']
    composer=json.loads(git('show',ref+':composer.json'));assert composer['name']=='aufeinwort/semantic-wikischema'
    installer=git('show',ref+':installer/install.py');assert ('VERSION = '+repr(version)).encode()in installer
    source_name=f'SemanticWikiSchema-{version}-source.zip';installer_name=f'SemanticWikiSchema-{version}-installer.zip'
    source=git('archive','--format=zip','--prefix=SemanticWikiSchema/',ref);(output/source_name).write_bytes(source)
    with zipfile.ZipFile(io.BytesIO(source)) as archive:
        assert archive.testzip()is None
        names=archive.namelist();assert not any('/node_modules/'in n or n.endswith('/node_modules') or '/vendor/'in n or n.endswith('.DS_Store')for n in names)
        assert not any(stat.S_ISLNK(item.external_attr >> 16) for item in archive.infolist())
        entries={n[len('SemanticWikiSchema/'):]:archive.read(n)for n in names if not n.endswith('/')}
    required=['extension.json','composer.json','src/SWSExtension.php','SchemaBaseBridge/extension.json','resources/ext.sws/dist/sws.js','graph-explorer/envendpoint.html','graph-explorer-dev/source/swsSchemaQueries.js','publication/DISTRIBUTION-SHA256SUMS']
    for name in required:assert name in entries,name
    lines=entries['publication/DISTRIBUTION-SHA256SUMS'].decode().splitlines()
    assert {line.split('  ',1)[1] for line in lines} == set(entries) - {'publication/DISTRIBUTION-SHA256SUMS'}
    for line in lines:
        digest,name=line.split('  ',1);assert hashlib.sha256(entries[name]).hexdigest()==digest,name
    payload={'install.py':installer,'README.md':git('show',ref+':installer/README.md'),'COPYING':entries['COPYING'],
             'VERSION.json':(json.dumps({'version':version,'commit':ref,'mode':'first-installation','package':composer['name']},indent=2)+'\n').encode()}
    for name,data in entries.items():
        if name.startswith('docs/operations/install/')or name in ['publication/apache-sws.example.conf','publication/LocalSettings.SemanticWikiSchema.example.php']:
            payload[name]=data
    # Links outside this small installer payload point to the corresponding tagged source.
    for name,data in list(payload.items()):
        if not name.endswith('.md'):continue
        def link(match):
            value=match.group(1)
            if value.startswith(('http:','https:','mailto:','#')):return match.group(0)
            path,separator,anchor=value.partition('#');combined=PurePosixPath(name).parent/path;parts=[]
            for part in combined.parts:
                if part=='..':
                    if not parts:raise ValueError('Link outside package source')
                    parts.pop()
                elif part!='.':parts.append(part)
            normalized='/'.join(parts)
            if normalized in payload:return match.group(0)
            if normalized not in entries:raise ValueError('Missing installer documentation target: '+normalized)
            return '](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v'+version+'/'+normalized+(separator+anchor if separator else '')+')'
        payload[name]=re.sub(r'\]\(([^)]+)\)',link,data.decode()).encode()
    payload['SHA256SUMS']=(''.join(hashlib.sha256(data).hexdigest()+'  '+name+'\n'for name,data in sorted(payload.items()))).encode()
    with zipfile.ZipFile(output/installer_name,'w',zipfile.ZIP_DEFLATED) as archive:
        for name,data in sorted(payload.items()):
            info=zipfile.ZipInfo(f'SemanticWikiSchema-{version}-installer/'+name,date_time=(2026,11,13,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16;archive.writestr(info,data)
    with zipfile.ZipFile(output/installer_name)as archive:
        assert archive.testzip()is None
        base=f'SemanticWikiSchema-{version}-installer/'
        for line in archive.read(base+'SHA256SUMS').decode().splitlines():
            digest,name=line.split('  ',1);assert hashlib.sha256(archive.read(base+name)).hexdigest()==digest
    (output/'SHA256SUMS').write_text(''.join(hashlib.sha256((output/name).read_bytes()).hexdigest()+'  '+name+'\n'for name in [source_name,installer_name]))
    result={'version':version,'commit':ref,'sourceFiles':len(entries),'installerFiles':len(payload),'sourceManifestVerified':True,'installerManifestVerified':True,'archives':[source_name,installer_name]}
    (output/'ARCHIVE-VERIFICATION.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result,indent=2))


if __name__=='__main__':main()
