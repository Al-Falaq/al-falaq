#!/usr/bin/env python3
"""Export parity goldens from pinned nexusnv/al-falak Python source.

Usage:
    python tools/export_goldens.py --pin <sha> --path /path/to/al-falak --out tests/Goldens

M0 placeholder: validates args and prints reminder. M1 implements CSV export.
"""
import argparse

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--pin", required=True, help="Pinned Python commit SHA (PYTHON_PIN)")
    parser.add_argument("--path", required=True, help="Local path to Python repo (your choice: local path)")
    parser.add_argument("--out", default="tests/Goldens")
    args = parser.parse_args()
    print(f"TODO(M1): export goldens from {args.path} at {args.pin} into {args.out}")
    print("Appendix A freeze blocked until this runs and checksum is recorded.")

if __name__ == "__main__":
    main()
