#!/usr/bin/env python3
"""
Build a WordPress.org distribution zip for Thimbleform.

Usage (from plugin root):
  python bin/build-release.py
  python bin/build-release.py --out ../thimbleform-2.3.2.zip
"""

from __future__ import annotations

import argparse
import fnmatch
import os
import re
import sys
import zipfile
from pathlib import Path


def read_distignore(plugin_root: Path) -> list[str]:
    distignore = plugin_root / ".distignore"
    if not distignore.is_file():
        return []
    patterns: list[str] = []
    for raw in distignore.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        patterns.append(line)
    return patterns


def normalize_rel(path: str) -> str:
    return path.replace("\\", "/")


def match_pattern(rel_posix: str, pattern: str) -> bool:
    pattern = normalize_rel(pattern).rstrip("/")
    rel_posix = normalize_rel(rel_posix)

    if pattern.startswith("**/"):
        tail = pattern[3:]
        return fnmatch.fnmatch(rel_posix, tail) or fnmatch.fnmatch(
            os.path.basename(rel_posix), tail
        )

    if "**/" in pattern:
        prefix, tail = pattern.split("**/", 1)
        if rel_posix.startswith(prefix) and fnmatch.fnmatch(
            rel_posix[len(prefix) :], tail
        ):
            return True
        return fnmatch.fnmatch(rel_posix, pattern)

    # Plain name (e.g. .git, node_modules, bin) — file or whole directory tree.
    if "/" not in pattern and "*" not in pattern and "?" not in pattern:
        return rel_posix == pattern or rel_posix.startswith(pattern + "/")

    if "/" not in pattern and pattern.startswith("*"):
        return fnmatch.fnmatch(os.path.basename(rel_posix), pattern)

    # Path pattern may still exclude a directory tree (assets/css/admin/).
    if fnmatch.fnmatch(rel_posix, pattern):
        return True
    if "*" not in pattern and "?" not in pattern:
        return rel_posix.startswith(pattern + "/")
    # Glob directory trees: assets/css/admin/** via pattern assets/css/admin
    if pattern.endswith("/*"):
        return False
    return fnmatch.fnmatch(rel_posix, pattern + "/*") or fnmatch.fnmatch(
        rel_posix, pattern + "/**"
    )


def is_excluded(rel_posix: str, patterns: list[str]) -> bool:
    excluded = False
    for pattern in patterns:
        if pattern.startswith("!"):
            if match_pattern(rel_posix, pattern[1:]):
                excluded = False
            continue
        if match_pattern(rel_posix, pattern):
            excluded = True
    return excluded


def main_plugin_file(plugin_root: Path) -> str:
    return "thimbleform.php"


def read_version(plugin_root: Path) -> str:
    main_file = plugin_root / main_plugin_file(plugin_root)
    text = main_file.read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(.+?)\s*$", text, re.MULTILINE)
    if not match:
        raise RuntimeError(f"Could not read Version from {main_file.name}")
    return match.group(1).strip()


def collect_files(plugin_root: Path, patterns: list[str]) -> list[Path]:
    files: list[Path] = []
    for path in sorted(plugin_root.rglob("*")):
        if not path.is_file():
            continue
        rel = normalize_rel(str(path.relative_to(plugin_root)))
        if is_excluded(rel, patterns):
            continue
        files.append(path)
    return files


def build_zip(plugin_root: Path, output: Path) -> None:
    patterns = read_distignore(plugin_root)
    files = collect_files(plugin_root, patterns)
    slug = "thimbleform"
    output.parent.mkdir(parents=True, exist_ok=True)
    if output.exists():
        output.unlink()

    with zipfile.ZipFile(
        output,
        mode="w",
        compression=zipfile.ZIP_DEFLATED,
        compresslevel=9,
    ) as archive:
        for file_path in files:
            rel = file_path.relative_to(plugin_root).as_posix()
            arcname = f"{slug}/{rel}"
            info = zipfile.ZipInfo(arcname)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.flag_bits |= 0x800  # UTF-8 file names
            data = file_path.read_bytes()
            archive.writestr(info, data)

    print(f"Created: {output}")
    print(f"Files:   {len(files)}")
    print(f"Size:    {output.stat().st_size:,} bytes")


def verify_zip(output: Path, slug: str) -> None:
    with zipfile.ZipFile(output, "r") as archive:
        names = archive.namelist()
        if not names:
            raise RuntimeError("Zip is empty")
        main = f"{slug}/thimbleform.php"
        if main not in names:
            raise RuntimeError(f"Missing {main} in archive")
        bad = [name for name in names if "\\" in name]
        if bad:
            raise RuntimeError(f"Backslashes in zip paths: {bad[:3]}")
        archive.read(main)
    print(f"Verify:  OK ({slug}/thimbleform.php present, UTF-8 paths)")


def main() -> int:
    parser = argparse.ArgumentParser(description="Build Thimbleform release zip")
    parser.add_argument(
        "--root",
        default=str(Path(__file__).resolve().parents[1]),
        help="Plugin root directory",
    )
    parser.add_argument(
        "--out",
        default="",
        help="Output zip path (default: ../thimbleform-{version}.zip)",
    )
    args = parser.parse_args()

    plugin_root = Path(args.root).resolve()
    version = read_version(plugin_root)
    slug = "thimbleform"
    output = (
        Path(args.out).resolve()
        if args.out
        else (plugin_root.parent / f"{slug}-{version}.zip").resolve()
    )

    build_zip(plugin_root, output)
    verify_zip(output, slug)
    return 0


if __name__ == "__main__":
    sys.exit(main())
