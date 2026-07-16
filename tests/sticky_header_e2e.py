import os
import sys
from playwright.sync_api import sync_playwright

BASE_URL = os.environ.get("JAT_BASE_URL", "http://127.0.0.1:8080/")
USER_AGENT = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36"
)
CASES = (
    {"name": "desktop", "viewport": {"width": 1440, "height": 900}, "mobile": False},
    {"name": "mobile", "viewport": {"width": 390, "height": 844}, "mobile": True},
)

failures = 0


def check(condition, label, details=""):
    global failures
    suffix = f" — {details}" if details else ""
    if condition:
        print(f"PASS: {label}{suffix}")
        return
    failures += 1
    print(f"FAIL: {label}{suffix}", file=sys.stderr)


with sync_playwright() as playwright:
    browser = playwright.chromium.launch(
        headless=True,
        executable_path="/usr/bin/chromium",
        args=["--no-sandbox"],
    )

    for case in CASES:
        context = browser.new_context(
            viewport=case["viewport"],
            locale="ja-JP",
            user_agent=USER_AGENT,
        )
        page = context.new_page()
        response = page.goto(BASE_URL, wait_until="networkidle")
        if response is None or response.status != 200:
            raise AssertionError(
                f"{case['name']}: HTTP {response.status if response else 'no response'}"
            )

        header_selector = ".wp-site-blocks > header.wp-block-template-part"
        header = page.locator(header_selector)
        header.wait_for(state="visible")

        before = header.evaluate(
            """element => {
                const rect = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {top: rect.top, bottom: rect.bottom, height: rect.height, position: style.position};
            }"""
        )

        page.evaluate("window.scrollTo(0, 1000)")
        page.wait_for_timeout(150)

        after = header.evaluate(
            """element => {
                const rect = element.getBoundingClientRect();
                const style = getComputedStyle(element);
                return {top: rect.top, bottom: rect.bottom, height: rect.height, position: style.position};
            }"""
        )

        check(before["position"] == "sticky", f"{case['name']}: header uses sticky positioning", str(before))
        check(abs(after["top"]) <= 1, f"{case['name']}: header remains at viewport top after scroll", str(after))
        check(abs(after["height"] - before["height"]) <= 1, f"{case['name']}: header height is stable while scrolling")

        header_horizontal_metrics = header.evaluate(
            """element => {
                const rect = element.getBoundingClientRect();
                return {innerWidth: window.innerWidth, left: rect.left, right: rect.right, width: rect.width};
            }"""
        )
        header_fits_viewport = (
            header_horizontal_metrics["left"] >= -1
            and header_horizontal_metrics["right"] <= header_horizontal_metrics["innerWidth"] + 1
        )
        check(header_fits_viewport, f"{case['name']}: sticky header does not create horizontal overflow", str(header_horizontal_metrics))

        page.evaluate("window.scrollTo(0, 0)")
        page.wait_for_timeout(100)
        page.locator('a[href="#services"]').first.click()
        page.wait_for_timeout(150)

        anchor_metrics = page.evaluate(
            """selector => {
                const header = document.querySelector(selector);
                const target = document.querySelector('#services');
                if (!header || !target) return null;
                return {
                    headerBottom: header.getBoundingClientRect().bottom,
                    targetTop: target.getBoundingClientRect().top,
                    scrollY: window.scrollY,
                    scrollPaddingTop: getComputedStyle(document.documentElement).scrollPaddingTop,
                };
            }""",
            header_selector,
        )

        anchor_ok = bool(
            anchor_metrics
            and anchor_metrics["scrollY"] > 0
            and anchor_metrics["targetTop"] >= anchor_metrics["headerBottom"] - 1
        )
        check(anchor_ok, f"{case['name']}: #services anchor is not hidden behind the sticky header", str(anchor_metrics))

        if case["mobile"]:
            page.evaluate("window.scrollTo(0, 0)")
            open_button = page.locator(".wp-block-navigation__responsive-container-open")
            check(open_button.is_visible(), "mobile: navigation open button is visible")
            open_button.click()

            open_menu = page.locator(".wp-block-navigation__responsive-container.is-menu-open")
            open_menu.wait_for(state="visible")
            check(open_menu.is_visible(), "mobile: responsive navigation opens")

            menu_header_top = header.evaluate("element => element.getBoundingClientRect().top")
            check(abs(menu_header_top) <= 1, "mobile: header remains aligned while menu is open", f"top={menu_header_top}")

            page.locator(".wp-block-navigation__responsive-container-close").click()
            open_menu.wait_for(state="hidden")
            check(not open_menu.is_visible(), "mobile: responsive navigation closes")

        context.close()

    browser.close()

if failures:
    print(f"Sticky header E2E failed: {failures} assertion(s).", file=sys.stderr)
    raise SystemExit(1)

print("Sticky header E2E passed.")
