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
        "responsive_four_column": True,
        "wide_on_desktop": True,
        "check_hero_meta": True,
    },
    {
        "name": "locations",
        "path": "/",
        "grid": ".jat-grid--4:has(> .jat-location)",
        "cards": ".jat-grid--4:has(> .jat-location) > .jat-location",
        "next": ".jat-grid--4:has(> .jat-location) + .wp-block-buttons",
        "responsive_four_column": True,
        "wide_on_desktop": True,
        "max_card_height": 280,
        "max_card_ratio": 1.3,
        "minimum_following_gap": 44,
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


def collect_hero_meta(page):
    container = page.locator(".jat-hero__meta").first
    items = container.locator(":scope > span")
    if items.count() != 4:
        raise AssertionError(f"hero meta: expected 4 items, found {items.count()}")

    container_box = rounded_box(container.bounding_box())
    item_boxes = [rounded_box(items.nth(index).bounding_box()) for index in range(items.count())]
    if container_box is None or any(box is None for box in item_boxes):
        raise AssertionError("hero meta: container or item is not visible")

    computed = container.evaluate(
        """element => ({
            display: getComputedStyle(element).display,
            gridTemplateColumns: getComputedStyle(element).gridTemplateColumns
        })"""
    )
    item_overflow = [
        items.nth(index).evaluate(
            """element => ({
                horizontal: element.scrollWidth - element.clientWidth,
                vertical: element.scrollHeight - element.clientHeight
            })"""
        )
        for index in range(items.count())
    ]
    return {
        "container": container_box,
        "items": item_boxes,
        "computed": computed,
        "item_overflow": item_overflow,
    }


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

    geometry = {
        "url": page.url,
        "grid": grid_box,
        "cards": card_boxes,
        "computed_cards": computed_cards,
        "next": next_box,
        "document": document_size,
    }
    if case.get("check_hero_meta"):
        geometry["hero_meta"] = collect_hero_meta(page)
    return geometry


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
    minimum_following_gap = case.get("minimum_following_gap", 24)
    if following_gap < minimum_following_gap:
        errors.append(
            f"following content gap={following_gap:.2f}px, "
            f"expected at least {minimum_following_gap}px"
        )

    horizontal_overflow = geometry["document"]["horizontalOverflow"]
    if horizontal_overflow > 1:
        errors.append(f"horizontal overflow={horizontal_overflow}px")

    if case.get("wide_on_desktop") and viewport_width >= 961:
        expected_width = min(1400, viewport_width - 48)
        if abs(grid["width"] - expected_width) > 2:
            errors.append(
                f"grid width={grid['width']:.2f}px, expected {expected_width:.2f}px"
            )

    if case.get("max_card_height") is not None:
        tallest_card = max(card["height"] for card in cards)
        if tallest_card > case["max_card_height"]:
            errors.append(
                f"card height={tallest_card:.2f}px, expected at most {case['max_card_height']}px"
            )

    if case.get("max_card_ratio") is not None:
        tallest_ratio = max(card["height"] / card["width"] for card in cards)
        if tallest_ratio > case["max_card_ratio"]:
            errors.append(
                f"card height/width ratio={tallest_ratio:.2f}, "
                f"expected at most {case['max_card_ratio']}"
            )

    unique_columns = len({round(card["x"]) for card in cards})
    expected_columns = None
    if case.get("responsive_four_column"):
        expected_columns = 1 if viewport_width <= 680 else (2 if viewport_width <= 960 else 4)
    if case["name"] == "service":
        expected_columns = 1 if viewport_width <= 680 else 2
    if expected_columns is not None and unique_columns != expected_columns:
        errors.append(f"columns={unique_columns}, expected {expected_columns}")

    hero_summary = None
    if case.get("check_hero_meta"):
        hero = geometry["hero_meta"]
        if hero["computed"]["display"] != "grid":
            errors.append(
                f"hero meta display={hero['computed']['display']}, expected grid"
            )
        hero_columns = len({round(item["x"]) for item in hero["items"]})
        hero_rows = len({round(item["y"]) for item in hero["items"]})
        expected_hero_columns = 1 if viewport_width <= 480 else (2 if viewport_width <= 960 else 4)
        expected_hero_rows = 4 // expected_hero_columns
        if hero_columns != expected_hero_columns or hero_rows != expected_hero_rows:
            errors.append(
                f"hero meta columns/rows={hero_columns}/{hero_rows}, "
                f"expected {expected_hero_columns}/{expected_hero_rows}"
            )
        for index, overflow in enumerate(hero["item_overflow"], start=1):
            if overflow["horizontal"] > 1:
                errors.append(
                    f"hero meta item {index} horizontal overflow={overflow['horizontal']}px"
                )
        hero_summary = {
            "columns": hero_columns,
            "rows": hero_rows,
            "grid_template_columns": hero["computed"]["gridTemplateColumns"],
        }

    if errors:
        raise AssertionError(f"{case['name']} {viewport_name}: " + "; ".join(errors))

    return {
        "card_bottom": round(card_bottom, 2),
        "grid_bottom": round(grid_bottom, 2),
        "following_gap": round(following_gap, 2),
        "columns": unique_columns,
        "horizontal_overflow": horizontal_overflow,
        "hero_meta": hero_summary,
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
                    f"hero={summary['hero_meta']} "
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
