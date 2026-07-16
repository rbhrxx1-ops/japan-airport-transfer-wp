#!/usr/bin/env python3
"""Validate home/service card geometry across desktop, tablet, and mobile widths."""

import argparse
import json
from pathlib import Path
from urllib.parse import urljoin

from playwright.sync_api import sync_playwright

VIEWPORTS = (
    ("desktop-1440", 1440, 1000),
    ("desktop-1280", 1280, 900),
    ("tablet-1024", 1024, 900),
    ("tablet-768", 768, 1024),
    ("mobile-390", 390, 844),
)

CASES = (
    {
        "name": "home",
        "path": "/",
        "grid": "#services .jat-grid--4",
        "cards": "#services .jat-grid--4 > .jat-card",
        "next": "#services + section",
    },
    {
        "name": "service",
        "path": "/service/",
        "grid": ".jat-grid--2",
        "cards": ".jat-grid--2 > .jat-card",
        "next_heading": "個別相談サービス",
    },
)

USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/138.0.0.0 Safari/537.36"
)


def parse_args():
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", default="http://127.0.0.1:8080")
    parser.add_argument(
        "--output-dir",
        default=".runtime/card-layout-e2e",
        help="Directory for screenshots and geometry JSON.",
    )
    return parser.parse_args()


def rounded_box(box):
    if box is None:
        return None
    return {key: round(value, 2) for key, value in box.items()}


def overlap_area(first, second):
    overlap_width = min(first["x"] + first["width"], second["x"] + second["width"]) - max(
        first["x"], second["x"]
    )
    overlap_height = min(first["y"] + first["height"], second["y"] + second["height"]) - max(
        first["y"], second["y"]
    )
    return round(max(0, overlap_width) * max(0, overlap_height), 2)


def collect_geometry(page, case):
    grid = page.locator(case["grid"]).first
    cards = page.locator(case["cards"])
    if cards.count() != 4:
        raise AssertionError(f"{case['name']}: expected 4 cards, found {cards.count()}")

    grid_box = rounded_box(grid.bounding_box())
    card_boxes = [rounded_box(cards.nth(index).bounding_box()) for index in range(cards.count())]
    if grid_box is None or any(box is None for box in card_boxes):
        raise AssertionError(f"{case['name']}: grid or card is not visible")

    if "next" in case:
        next_box = rounded_box(page.locator(case["next"]).first.bounding_box())
    else:
        next_box = rounded_box(
            page.get_by_role("heading", name=case["next_heading"], exact=True).bounding_box()
        )
    if next_box is None:
        raise AssertionError(f"{case['name']}: following content is not visible")

    computed_cards = [
        cards.nth(index).evaluate(
            """card => ({
                boxSizing: getComputedStyle(card).boxSizing,
                cssHeight: getComputedStyle(card).height,
                paddingTop: getComputedStyle(card).paddingTop,
                paddingBottom: getComputedStyle(card).paddingBottom
            })"""
        )
        for index in range(cards.count())
    ]
    document_size = page.evaluate(
        """() => ({
            clientWidth: document.documentElement.clientWidth,
            scrollWidth: document.documentElement.scrollWidth,
            horizontalOverflow:
                document.documentElement.scrollWidth - document.documentElement.clientWidth
        })"""
    )

    return {
        "url": page.url,
        "grid": grid_box,
        "cards": card_boxes,
        "computed_cards": computed_cards,
        "next": next_box,
        "document": document_size,
    }


def assert_geometry(case, viewport_name, viewport_width, geometry):
    errors = []
    cards = geometry["cards"]
    grid = geometry["grid"]
    next_box = geometry["next"]

    for index, computed in enumerate(geometry["computed_cards"], start=1):
        if computed["boxSizing"] != "border-box":
            errors.append(f"card {index} box-sizing={computed['boxSizing']}, expected border-box")

    overlaps = []
    for first in range(len(cards)):
        for second in range(first + 1, len(cards)):
            area = overlap_area(cards[first], cards[second])
            if area > 0.5:
                overlaps.append(f"{first + 1}-{second + 1}:{area}px²")
    if overlaps:
        errors.append("card overlaps=" + ", ".join(overlaps))

    card_bottom = max(card["y"] + card["height"] for card in cards)
    grid_bottom = grid["y"] + grid["height"]
    if abs(card_bottom - grid_bottom) > 1.5:
        errors.append(
            f"grid bottom {grid_bottom:.2f}px does not wrap card bottom {card_bottom:.2f}px"
        )

    following_gap = next_box["y"] - card_bottom
    if following_gap < 24:
        errors.append(f"following content gap={following_gap:.2f}px, expected at least 24px")

    horizontal_overflow = geometry["document"]["horizontalOverflow"]
    if horizontal_overflow > 1:
        errors.append(f"horizontal overflow={horizontal_overflow}px")

    if case["name"] == "home" and viewport_width >= 961:
        expected_width = min(1400, viewport_width - 48)
        if abs(grid["width"] - expected_width) > 2:
            errors.append(
                f"home grid width={grid['width']:.2f}px, expected {expected_width:.2f}px"
            )

    unique_columns = len({round(card["x"]) for card in cards})
    expected_columns = 1 if viewport_width <= 720 else (2 if viewport_width <= 960 else None)
    if case["name"] == "home" and viewport_width > 960:
        expected_columns = 4
    if case["name"] == "service" and viewport_width > 720:
        expected_columns = 2
    if expected_columns is not None and unique_columns != expected_columns:
        errors.append(f"columns={unique_columns}, expected {expected_columns}")

    if errors:
        raise AssertionError(f"{case['name']} {viewport_name}: " + "; ".join(errors))

    return {
        "card_bottom": round(card_bottom, 2),
        "grid_bottom": round(grid_bottom, 2),
        "following_gap": round(following_gap, 2),
        "columns": unique_columns,
        "horizontal_overflow": horizontal_overflow,
    }


def main():
    args = parse_args()
    output_dir = Path(args.output_dir).resolve()
    output_dir.mkdir(parents=True, exist_ok=True)
    base_url = args.base_url.rstrip("/") + "/"
    results = []

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            executable_path="/usr/bin/chromium",
            args=["--no-sandbox", "--disable-dev-shm-usage"],
        )
        for viewport_name, width, height in VIEWPORTS:
            context = browser.new_context(
                viewport={"width": width, "height": height},
                user_agent=USER_AGENT,
                locale="ja-JP",
            )
            page = context.new_page()
            for case in CASES:
                target_url = urljoin(base_url, case["path"].lstrip("/"))
                response = page.goto(target_url, wait_until="networkidle")
                if response is None or response.status >= 400:
                    raise AssertionError(
                        f"{case['name']} {viewport_name}: HTTP "
                        f"{response.status if response else 'no response'}"
                    )
                page.evaluate("document.fonts ? document.fonts.ready : Promise.resolve()")
                geometry = collect_geometry(page, case)
                summary = assert_geometry(case, viewport_name, width, geometry)
                screenshot = output_dir / f"{case['name']}-{viewport_name}.png"
                page.screenshot(path=screenshot, full_page=True)
                results.append(
                    {
                        "page": case["name"],
                        "viewport": viewport_name,
                        "width": width,
                        "height": height,
                        "summary": summary,
                        "geometry": geometry,
                        "screenshot": str(screenshot),
                    }
                )
                print(
                    f"PASS {case['name']} {viewport_name}: "
                    f"columns={summary['columns']} "
                    f"gap={summary['following_gap']}px "
                    f"horizontal_overflow={summary['horizontal_overflow']}px"
                )
            context.close()
        browser.close()

    report_path = output_dir / "geometry.json"
    report_path.write_text(
        json.dumps(results, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print(f"CARD_LAYOUT_E2E_PASS cases={len(results)} report={report_path}")


if __name__ == "__main__":
    main()
