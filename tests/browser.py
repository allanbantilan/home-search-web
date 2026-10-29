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
    mobile.evaluate("async () => await Promise.all([...document.images].map(image => { image.loading = 'eager'; return image.decode().catch(() => {}); }))")
    width = mobile.evaluate("({viewport: innerWidth, page: document.documentElement.scrollWidth})")
    print("Mobile widths:", width)
    mobile.screenshot(path=str(out / "mobile-browser.png"), full_page=True)
    assert width["page"] <= width["viewport"], "Mobile page overflows horizontally"
    mobile.locator('.menu-toggle').click()
    assert mobile.get_by_role('navigation', name='Primary navigation').get_by_role('link', name='Saved homes 0').is_visible()
    assert mobile.get_by_role('navigation', name='Primary navigation').get_by_role('link', name='Compare homes 0').is_visible()
    print('PASS mobile navigation')
    mobile.set_viewport_size({"width": 320, "height": 750})
    assert mobile.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth")
    tablet = browser.new_page(viewport={"width": 768, "height": 900})
    tablet.goto(base + "/", wait_until="domcontentloaded")
    assert tablet.evaluate("document.documentElement.scrollWidth <= document.documentElement.clientWidth")
    assert tablet.locator('.menu-toggle').is_visible()
    print('PASS narrow and tablet widths')

    desktop.goto(base + "/properties/", wait_until="domcontentloaded")
    desktop.locator("#filter-location").fill("Makati")
    desktop.locator("#result-count").get_by_text("1 home found").wait_for(timeout=10000)
    assert desktop.locator("#property-results .property-card").count() == 1
    print("PASS live search")

    desktop.locator('[data-view="map"]').click()
    desktop.locator(".leaflet-marker-icon").first.wait_for(timeout=10000)
    print("PASS map marker")

    desktop.locator("[data-favorite]").first.click()
    assert desktop.locator("[data-favorite-count]").first.inner_text() == "1"
    desktop.goto(base + "/favorites/", wait_until="domcontentloaded")
    desktop.locator("#favorites-content .property-card").first.wait_for(timeout=10000)
    print("PASS saved home")

    desktop.goto(base + "/properties/", wait_until="domcontentloaded")
    desktop.locator("[data-compare]").first.check()
    assert desktop.locator("[data-compare-count]").first.inner_text() == "1"
    desktop.goto(base + "/compare/", wait_until="domcontentloaded")
    desktop.locator(".compare-table").wait_for(timeout=10000)
    print("PASS compare")

    desktop.goto(base + "/properties/makati-garden-residence/", wait_until="domcontentloaded")
    desktop.locator("#interest-rate").fill("0")
    assert desktop.locator("#monthly-payment").inner_text() == "₱60,000"
    print("PASS payment calculator")
    browser.close()
