#!/usr/bin/env python3
"""Local-only end-to-end checks for the draft recruitment page preview."""

from pathlib import Path
from playwright.sync_api import sync_playwright

BASE_URL = "http://127.0.0.1:8080"
RECRUIT_URL = f"{BASE_URL}/recruit/"
SCREENSHOT_DIR = Path("/tmp/jat-recruit-e2e")


def assert_page(page, viewport_name: str) -> None:
    page.goto(RECRUIT_URL, wait_until="networkidle")
    assert page.title().startswith("採用情報"), page.title()
    assert page.locator("h1").inner_text().strip() == "採用情報"

    required_text = (
        "募集職種・業務内容",
        "求める人物像",
        "暫定募集条件",
        "一般アルバイト給与",
        "シフトリーダー固定給",
        "待遇・福利厚生",
        "試用期間",
        "応募方法",
    )
    body_text = page.locator("main").inner_text()
    for text in required_text:
        assert text in body_text, f"{viewport_name}: missing {text}"

    assert page.locator("main.jat-recruit-page").count() == 1
    contact_cta = page.locator('main a[href="/contact/"]')
    assert contact_cta.count() == 1
    assert contact_cta.inner_text().strip() == "採用について問い合わせる"
    assert page.locator('header a[href="/recruit/"]').count() == 0
    assert page.locator('footer a[href="/recruit/"]').count() == 0
    if viewport_name == "mobile":
        mobile_actions = page.locator(".jat-mobile-actions")
        assert mobile_actions.count() == 1
        assert mobile_actions.evaluate("el => getComputedStyle(el).display") == "none"

    page.evaluate("window.scrollTo(0, 900)")
    page.wait_for_timeout(250)
    header_box = page.locator("header.wp-block-template-part").bounding_box()
    assert header_box is not None
    assert abs(header_box["y"]) <= 1, f"{viewport_name}: sticky header y={header_box['y']}"

    overflow = page.evaluate(
        """() => ({
          viewport: document.documentElement.clientWidth,
          documentWidth: document.documentElement.scrollWidth,
          tableWidth: document.querySelector('main .wp-block-table')?.scrollWidth || 0,
          tableViewport: document.querySelector('main .wp-block-table')?.clientWidth || 0
        })"""
    )
    assert overflow["documentWidth"] <= overflow["viewport"] + 1, overflow
    if viewport_name == "mobile":
        assert overflow["tableWidth"] > overflow["tableViewport"], overflow

    SCREENSHOT_DIR.mkdir(parents=True, exist_ok=True)
    page.screenshot(path=str(SCREENSHOT_DIR / f"recruit-{viewport_name}.png"), full_page=True)


def main() -> None:
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            executable_path="/usr/bin/chromium",
            args=["--no-sandbox"],
        )
        try:
            desktop = browser.new_page(viewport={"width": 1440, "height": 900})
            assert_page(desktop, "desktop")
            desktop.close()

            mobile = browser.new_page(
                viewport={"width": 390, "height": 844},
                is_mobile=True,
                device_scale_factor=1,
            )
            assert_page(mobile, "mobile")
            mobile.close()
        finally:
            browser.close()

    print("RECRUIT_PAGE_E2E=PASS")


if __name__ == "__main__":
    main()
