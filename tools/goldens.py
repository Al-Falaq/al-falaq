#!/usr/bin/env python3
"""One golden-regeneration entrypoint (per-target contracts preserved).

Usage from the repo root:
    python3 tools/goldens.py --list
    python3 tools/goldens.py <target> [args...]

Targets (contracts live with each generator):
    prayer-pin        pin-parity prayer vectors (needs --pin/--path/--out)
    hijri             month-start tables + Hijri goldens (writes in place)
    prayer-self       drift-gated Tehran/Morocco/UOIF goldens (php, in place)
    qibla-spherical   CSV to stdout (diff against committed)
    qibla-wgs84       CSV to stdout (diff against committed)
    moonsighting      crescent characterization (asserts + diagnostics)

Extra args after <target> pass through verbatim.
"""

import subprocess
import sys
from pathlib import Path

TARGETS = {
    "prayer-pin": (["python3", "tools/export_goldens.py"], "pin-parity prayer vectors from PYTHON_PIN"),
    "hijri": (["python3", "tools/gen_hijri.py"], "month-start tables + Hijri goldens"),
    "prayer-self": (["php", "tools/gen_self_prayer_goldens.php"], "drift-gated Tehran/Morocco/UOIF goldens"),
    "qibla-spherical": (["python3", "tools/gen_qibla_spherical.py"], "spherical Qibla CSV to stdout"),
    "qibla-wgs84": (["python3", "tools/gen_qibla_wgs84.py"], "WGS84 Qibla CSV to stdout"),
    "moonsighting": (["python3", "tools/gen_moonsighting.py"], "crescent characterization goldens"),
}


def main() -> int:
    if len(sys.argv) < 2 or sys.argv[1] in ("--list", "-l", "help", "--help"):
        print("golden targets (contracts preserved per generator):")
        for name, (_, contract) in TARGETS.items():
            print(f"  {name:16} {contract}")
        return 0

    target, rest = sys.argv[1], sys.argv[2:]
    if target not in TARGETS:
        print(f"unknown target {target!r}; try --list", file=sys.stderr)
        return 2

    root = Path(__file__).resolve().parent.parent
    cmd, _ = TARGETS[target]
    # Audit note (opengrep subprocess flags): cmd comes from the fixed TARGETS
    # table above (never from argv — only the key is looked up), argv extras
    # pass through verbatim, and the list form (no shell) means no shell
    # interpretation. Threat model is the local maintainer's own shell.
    completed = subprocess.run(cmd + rest, cwd=root)
    return completed.returncode


if __name__ == "__main__":
    raise SystemExit(main())
