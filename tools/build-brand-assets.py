#!/usr/bin/env python3
"""Build deterministic Meet & Link web brand assets from user-authorized sources."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path

from PIL import Image, ImageChops, ImageStat

ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = Path("/home/ubuntu/upload")
OUTPUT_DIR = ROOT / "wp-content/themes/jat-meet-theme/assets/images/brand"
SOURCES = {
    "light": SOURCE_DIR / "meet_and_link_logo_light.webp",
    "dark": SOURCE_DIR / "meet_and_link_logo_dark.webp",
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def background_color(image: Image.Image) -> tuple[int, int, int]:
    rgb = image.convert("RGB")
    width, height = rgb.size
    points = [
        rgb.getpixel((0, 0)),
        rgb.getpixel((width - 1, 0)),
        rgb.getpixel((0, height - 1)),
        rgb.getpixel((width - 1, height - 1)),
    ]
    return tuple(int(round(sum(point[channel] for point in points) / 4)) for channel in range(3))


def content_mask(image: Image.Image, background: tuple[int, int, int], threshold: int = 24) -> Image.Image:
    rgb = image.convert("RGB")
    bg = Image.new("RGB", rgb.size, background)
    difference = ImageChops.difference(rgb, bg).convert("L")
    return difference.point(lambda value: 255 if value >= threshold else 0)


def trim_logo(image: Image.Image) -> tuple[Image.Image, tuple[int, int, int], tuple[int, int, int, int]]:
    background = background_color(image)
    mask = content_mask(image, background)
    bbox = mask.getbbox()
    if bbox is None:
        raise RuntimeError("No logo content detected")

    left, top, right, bottom = bbox
    pad_x = max(16, int((right - left) * 0.025))
    pad_y = max(16, int((bottom - top) * 0.12))
    crop_box = (
        max(0, left - pad_x),
        max(0, top - pad_y),
        min(image.width, right + pad_x),
        min(image.height, bottom + pad_y),
    )
    return image.crop(crop_box).convert("RGB"), background, crop_box


def find_symbol_split(mask: Image.Image) -> int:
    width, height = mask.size
    occupancy = []
    pixels = mask.load()
    for x in range(width):
        occupied = sum(1 for y in range(height) if pixels[x, y] > 0)
        occupancy.append(occupied / height)

    start = int(width * 0.18)
    end = int(width * 0.42)
    min_gap = max(8, int(width * 0.012))
    run_start = None
    candidates: list[tuple[int, int]] = []
    for x in range(start, end):
        if occupancy[x] < 0.012:
            run_start = x if run_start is None else run_start
        elif run_start is not None:
            if x - run_start >= min_gap:
                candidates.append((run_start, x))
            run_start = None
    if run_start is not None and end - run_start >= min_gap:
        candidates.append((run_start, end))

    if not candidates:
        return int(width * 0.27)
    gap_start, gap_end = max(candidates, key=lambda item: item[1] - item[0])
    return (gap_start + gap_end) // 2


def fit_square(image: Image.Image, background: tuple[int, int, int], size: int) -> Image.Image:
    image = image.convert("RGB")
    padding = max(2, int(size * 0.12))
    available = size - 2 * padding
    scale = min(available / image.width, available / image.height)
    resized = image.resize(
        (max(1, int(image.width * scale)), max(1, int(image.height * scale))),
        Image.Resampling.LANCZOS,
    )
    canvas = Image.new("RGB", (size, size), background)
    canvas.paste(resized, ((size - resized.width) // 2, (size - resized.height) // 2))
    return canvas


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    manifest: dict[str, object] = {"sources": {}, "outputs": {}}
    processed: dict[str, tuple[Image.Image, tuple[int, int, int]]] = {}

    for variant, source in SOURCES.items():
        if not source.is_file():
            raise FileNotFoundError(source)
        with Image.open(source) as raw:
            trimmed, background, crop_box = trim_logo(raw)

        output = OUTPUT_DIR / f"meet-and-link-{variant}.webp"
        trimmed.save(output, "WEBP", quality=92, method=6)
        processed[variant] = (trimmed, background)
        manifest["sources"][variant] = {
            "path": str(source),
            "sha256": sha256(source),
            "size": list(Image.open(source).size),
            "crop_box": list(crop_box),
            "background": list(background),
        }
        manifest["outputs"][output.name] = {
            "sha256": sha256(output),
            "size": list(trimmed.size),
            "bytes": output.stat().st_size,
        }

    dark_logo, dark_background = processed["dark"]
    dark_mask = content_mask(dark_logo, dark_background)
    split = find_symbol_split(dark_mask)
    symbol_mask = dark_mask.crop((0, 0, split, dark_logo.height))
    symbol_bbox = symbol_mask.getbbox()
    if symbol_bbox is None:
        raise RuntimeError("No symbol content detected")
    left, top, right, bottom = symbol_bbox
    symbol = dark_logo.crop((left, top, right, bottom))

    icon_specs = {
        "meet-and-link-icon-32.png": 32,
        "meet-and-link-icon-180.png": 180,
        "meet-and-link-icon-192.png": 192,
        "meet-and-link-icon-512.png": 512,
    }
    for name, size in icon_specs.items():
        icon = fit_square(symbol, dark_background, size)
        output = OUTPUT_DIR / name
        icon.save(output, "PNG", optimize=True)
        manifest["outputs"][name] = {
            "sha256": sha256(output),
            "size": [size, size],
            "bytes": output.stat().st_size,
        }

    manifest["icon_symbol_split_x"] = split
    manifest_path = OUTPUT_DIR / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps(manifest, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
