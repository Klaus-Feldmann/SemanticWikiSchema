#!/usr/bin/env python3
"""Generate optional HDTO Mapping JSON for explicitly supplied local schema IDs and field names."""
import argparse,copy,json,re
from pathlib import Path

def build(artwork,artist,museum,creator,keeper,image):
    ids=[artwork,artist,museum]
    if len(set(ids))!=3 or any(not re.fullmatch(r'S[1-9][0-9]{0,17}',v)for v in ids):
        raise ValueError('Supply three distinct canonical local S-IDs.')
    fields=[creator,keeper,image]
    if any(not v.strip()for v in fields) or len({v.strip()for v in fields})!=3:
        raise ValueError('Supply three distinct nonempty field names for the artwork schema.')
    template=json.loads(Path(__file__).with_name('HDTO.named-example.json').read_text())
    entries=template.pop('schemas');art=entries['Artwork']
    art['properties']={creator.strip():art['properties']['Creator'],keeper.strip():art['properties']['Located at'],image.strip():art['properties']['Image']}
    template['schemas']={}
    template['schemaIds']={artwork:art,artist:entries['Artist'],museum:entries['Museum']}
    return template

def main():
    p=argparse.ArgumentParser(description=__doc__)
    for key in ['artwork','artist','museum','creator','keeper','image']:p.add_argument('--'+key,required=True)
    a=p.parse_args()
    try:result=build(a.artwork,a.artist,a.museum,a.creator,a.keeper,a.image)
    except ValueError as e:p.error(str(e))
    print(json.dumps(result,ensure_ascii=False,indent=2))
if __name__=='__main__':main()
