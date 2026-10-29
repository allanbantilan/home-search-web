"""Browser checks for the isolated Docker site (requires Playwright and Chrome)."""

import sys
from pathlib import Path
from playwright.sync_api import sync_playwright

base = sys.argv[1].rstrip("/")
out = Path(".docker-test")
chrome = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True, executable_path=chrome)
    desktop = browser.new_page(viewport={"width": 1440, "height": 900})
    desktop.goto(base + "/", wait_until="domcontentloaded")
    desktop.screenshot(path=str(out / "homepage-browser.png"), full_page=True)

    mobile = browser.new_page(viewport={"width": 390, "height": 844}, is_mobile=True, device_scale_factor=1)
    mobile.goto(base + "/", wait_until="domcontentloaded")
    width = mobile.evaluate("({viewport: innerWidth, page: document.documentElement.scrollWidth})")
    print("Mobile widths:", width)
    mobile.screenshot(path=str(out / "mobile-browser.png"), full_page=True)
    assert width["page"] <= width["viewport"], "Mobile page overflows horizontally"

    desktop.goto(base + "/properties/", wait_until="domcontentloaded")
    desktop.locator("#filter-location").fill("Makati")
    desktop.locator("#result-count").get_by_text("1 home found").wait_for(timeout=10000)
    assert desktop.locator("#property-results .property-card").count() == 1
    print("PASS live search")

    desktop.locator('[data-view="map"]').click()
    desktop.locator(".leaflet-marker-icon").first.wait_for(timeout=10000)
    print("PASS map marker")

    desktop.locator("[data-favorite]").first.click()
    assert desktop.locator("[data-favorite-count]").inner_text() == "1"
    desktop.goto(base + "/favorites/", wait_until="domcontentloaded")
    desktop.locator("#favorites-content .property-card").first.wait_for(timeout=10000)
    print("PASS saved home")

    desktop.goto(base + "/properties/", wait_until="domcontentloaded")
    desktop.locator("[data-compare]").first.check()
    assert desktop.locator("[data-compare-count]").inner_text() == "1"
    desktop.goto(base + "/compare/", wait_until="domcontentloaded")
    desktop.locator(".compare-table").wait_for(timeout=10000)
    print("PASS compare")

    desktop.goto(base + "/properties/makati-garden-residence/", wait_until="domcontentloaded")
    desktop.locator("#interest-rate").fill("0")
    assert desktop.locator("#monthly-payment").inner_text() == "₱60,000"
    print("PASS payment calculator")
    browser.close()
