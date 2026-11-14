#!/usr/bin/env python3
"""Actual Composer install with a controlled MediaWiki directory layout, not a core runtime test."""
from pathlib import Path
import argparse, importlib.util, json, os, shutil, subprocess, sys, tempfile
sys.dont_write_bytecode = True


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--published-vcs',action='store_true');parser.add_argument('--php',default='php');parser.add_argument('--composer',default='composer');args=parser.parse_args()
    candidate=Path(__file__).resolve().parents[2]/'SEMANTICWIKISCHEMA-VERSION.json'
    if candidate.is_file() and json.loads(candidate.read_text()).get('publicationAllowed') is False:
        print('SKIPPED: unpublished candidate; no Composer/GitHub first installation attempted.')
        return
    php=shutil.which(args.php);composer=shutil.which(args.composer)
    if not php or not composer:raise RuntimeError('PHP and Composer required')
    source=Path(__file__).resolve().parents[2]
    with tempfile.TemporaryDirectory(prefix='sws-composer-first-') as folder:
        base=Path(folder);root=base/'wiki';root.mkdir()
        config={'name':'local/sws-first-install-check','require':{'wikimedia/composer-merge-plugin':'^2.1','psr/log':'^3'},'extra':{'merge-plugin':{'include':['composer.local.json'],'recurse':True,'merge-extra':True}},'config':{'allow-plugins':{'wikimedia/composer-merge-plugin':True,'composer/installers':True,'php-http/discovery':False}}}
        (root/'composer.json').write_text(json.dumps(config));(root/'composer.local.json').write_text(json.dumps({'require':{'psr/log':'^3'},'extra':{'unrelated-setting':'preserved'}}))
        for name in ['maintenance/run.php','includes/DefaultSettings.php','LocalSettings.php']:
            path=root/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_text('<?php /* controlled layout, no running wiki */')
        subprocess.run([php,composer,'install','--no-dev','--no-scripts','--no-progress','--no-interaction'],cwd=root,check=True)
        before=json.loads((root/'composer.lock').read_text());before_versions={p['name']:p['version']for p in before['packages']}
        package=base/'package';shutil.copytree(source,package,ignore=shutil.ignore_patterns('.git','vendor','node_modules','__pycache__','.DS_Store'))
        spec=importlib.util.spec_from_file_location('sws_install',source/'installer/install.py');module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
        # The release tag need not be public for a candidate check. Use the actual candidate files.
        if not args.published_vcs:
            module.REPOSITORY={'type':'path','url':str(package),'options':{'symlink':False,'versions':{module.PACKAGE:module.VERSION}}}
        root,changes=module.prepare(root);module.apply(root,changes,php,composer,base/'private-backups')
        local=json.loads((root/'composer.local.json').read_text());assert local['extra']['unrelated-setting']=='preserved';assert local['require']['psr/log']=='^3'
        assert (root/'LocalSettings.php').read_text()=='<?php /* controlled layout, no running wiki */'
        check=r'require "vendor/autoload.php"; if(!class_exists("Opis\\JsonSchema\\Validator")||!class_exists("Laudis\\Neo4j\\ClientBuilder"))exit(2);echo (new ProfessionalWiki\SWS\Domain\Identity\SchemaId("S1"))->text," ",(new ProfessionalWiki\SWS\Domain\Identity\EntityId("E1"))->text,"\n";'
        subprocess.run([php,'-r',check],cwd=root,check=True)
        lock=json.loads((root/'composer.lock').read_text());versions={p['name']:p['version']for p in lock['packages']}
        assert versions[module.PACKAGE]==module.VERSION
        assert all(versions[name]==version for name,version in before_versions.items())
        assert (root/'extensions/SemanticWikiSchema/graph-explorer/envendpoint.html').is_file()
        print(json.dumps({'result':'passed','repository':'published-vcs' if args.published_vcs else 'candidate-path','version':versions[module.PACKAGE],'packages':len(versions),'unrelatedConfig':'preserved','existingPackageVersions':'preserved','LocalSettings':'unchanged','autoload':'S1/E1 and runtime dependencies','scope':'Actual Composer; controlled MediaWiki directory layout, no core runtime/database test'},indent=2))


if __name__=='__main__':main()
