#!/usr/bin/env python3
"""Desktop and mobile visual/DOM acceptance checks for the Meet & Link brand."""

import os
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("SITE_BASE_URL", "http://127.0.0.1:8080").rstrip("/")
SCREENSHOT_DIR = Path(os.environ.get("BRAND_SCREENSHOT_DIR", "/tmp/meet-link-brand-e2e"))


def assert_brand(page, viewport_name: str) -> None:
    response = page.goto(f"{BASE_URL}/", wait_until="networkidle")
    assert response is not None and response.status == 200, response.status if response else "no response"
    assert page.title().startswith("Meet & Link"), page.title()

    header_logo = page.locator('header img[src*="meet-and-link-light.webp"]')
    footer_logo = page.locator('footer img[src*="meet-and-link-dark.webp"]')
    assert header_logo.count() == 1 and header_logo.is_visible(), f"{viewport_name}: header logo"
    assert footer_logo.count() == 1, f"{viewport_name}: footer logo"
    assert header_logo.evaluate("el => el.complete && el.naturalWidth > 0")
    footer_logo.scroll_into_view_if_needed()
    page.wait_for_function(
        "el => el.complete && el.naturalWidth > 0",
        arg=footer_logo.element_handle(),
        timeout=10_000,
    )
    assert footer_logo.evaluate("el => el.complete && el.naturalWidth > 0")
    expected_alt = "Meet & Link（ミート＆リンク） — EXECUTIVE & EVENT TRANSFER"
    assert header_logo.get_attribute("alt") == expected_alt
    assert footer_logo.get_attribute("alt") == expected_alt

    main_text = page.locator("main").inner_text()
    footer_text = page.locator("footer").inner_text()
    assert "MEET & LINK｜EXECUTIVE & EVENT TRANSFER" in main_text, repr(main_text[:1000])
    assert "会議・来賓の送迎と出迎え" in main_text
    assert "会議・来賓の送迎と出迎え" in footer_text
    visible_text = page.locator("body").inner_text()
    for old_brand in ("Japan Airport Transfer", "ミート＆センディング", "空港・駅 ミート＆センディングサービス"):
        assert old_brand not in visible_text, f"{viewport_name}: old brand remains: {old_brand}"

    for size in (32, 180, 192, 512):
        assert page.locator(f'head link[href*="meet-and-link-icon-{size}.png"]').count() >= 1, (
            viewport_name,
            size,
        )

    if viewport_name == "mobile":
        menu_button = page.locator("header .wp-block-navigation__responsive-container-open")
        assert menu_button.count() == 1 and menu_button.is_visible()
        menu_button.click()
        assert page.locator('header a[href="/service/"]').is_visible()
        close_button = page.locator("header .wp-block-navigation__responsive-container-close")
        assert close_button.count() == 1
        close_button.click()

    overflow = page.evaluate(
        """() => ({
          viewport: document.documentElement.clientWidth,
          documentWidth: document.documentElement.scrollWidth
        })"""
    )
    assert overflow["documentWidth"] <= overflow["viewport"] + 1, (viewport_name, overflow)

    SCREENSHOT_DIR.mkdir(parents=True, exist_ok=True)
    page.screenshot(path=str(SCREENSHOT_DIR / f"home-{viewport_name}.png"), full_page=True)


def main() -> None:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            executable_path="/usr/bin/chromium",
            args=["--no-sandbox"],
        )
        try:
            desktop_context = browser.new_context(
                viewport={"width": 1440, "height": 900},
                locale="ja-JP",
                user_agent=(
                    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
                    "(KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36"
                ),
            )
            desktop = desktop_context.new_page()
            assert_brand(desktop, "desktop")
            desktop_context.close()

            mobile_context = browser.new_context(
                viewport={"width": 390, "height": 844},
                is_mobile=True,
                device_scale_factor=1,
                locale="ja-JP",
                user_agent=(
                    "Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) "
                    "AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 "
                    "Mobile/15E148 Safari/604.1"
                ),
            )
            mobile = mobile_context.new_page()
            assert_brand(mobile, "mobile")
            mobile_context.close()
        finally:
            browser.close()

    print("MEET_AND_LINK_BRAND_E2E=PASS")


if __name__ == "__main__":
    main()
