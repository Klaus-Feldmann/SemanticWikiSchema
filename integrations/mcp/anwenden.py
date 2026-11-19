#!/usr/bin/env python3
"""Select one exact supported MCP starting state, then run the reversible patch installer."""
import argparse
import importlib.util
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('sws_patch_core', ROOT / 'patch_core.py')
core = importlib.util.module_from_spec(spec)
spec.loader.exec_module(core)

def main():
    parser = argparse.ArgumentParser(add_help=False)
    parser.add_argument('--ziel', required=True, type=Path)
    parser.add_argument('--wiederherstellen')
    args, _ = parser.parse_known_args()
    if args.wiederherstellen:
        core.main()
        return
    for profile in sorted((ROOT / 'profiles').iterdir()):
        core.ROOT = profile
        manifest = core.load_manifest()
        try:
            state = core.state(args.ziel, manifest)
        except ValueError:
            continue
        print('Recognized MCP profile:', profile.name, '(' + state + ')')
        core.main()
        return
    raise ValueError('Unknown MCP starting state; no files changed. This patch supports the checked SWS 0.5.0/r2/r3/r4 patches on MCP 0.21.0.')

if __name__ == '__main__':
    try:
        main()
    except (OSError, ValueError, KeyError) as error:
        raise SystemExit('STOP: ' + str(error))
