"""Smoke test the isolated Docker WordPress installation."""

import json
import re
import sys
from urllib.error import HTTPError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

base = sys.argv[1].rstrip("/")


def request(path, data=None):
    body = urlencode(data).encode() if data else None
    with urlopen(Request(base + path, data=body), timeout=20) as response:
        return response.url, response.read().decode("utf-8")


for path, marker in [
    ("/", "Find a home that feels like you."),
    ("/properties/", "Explore our properties."),
    ("/agents/", "Meet our agents."),
    ("/compare/", "Compare homes."),
    ("/favorites/", "Saved homes."),
    ("/contact/", "Send a message"),
    ("/properties/makati-garden-residence/", "Monthly payment estimate"),
    ("/agents/ana-reyes/", "Makati Garden Residence"),
]:
    _, html = request(path)
    assert marker in html, f"Missing {marker!r} on {path}"
    print("PASS", path)

_, raw = request("/wp-json/estate/v1/properties?location=Makati&bedrooms=3&min_price=15000000")
data = json.loads(raw)
assert data["total"] == 1 and data["items"][0]["title"] == "Makati Garden Residence"
assert data["items"][0]["lat"] == 14.5547
print("PASS filtered search and map coordinates")

_, raw = request("/wp-json/estate/v1/properties?type=house&max_price=10000000")
data = json.loads(raw)
assert data["total"] == 1 and data["items"][0]["title"] == "Cebu City Modern Loft"
print("PASS type and price filters")

try:
    request("/wp-json/estate/v1/properties?min_price=20000000&max_price=10000000")
except HTTPError as error:
    assert error.code == 400
else:
    raise AssertionError("Invalid price range was accepted")
print("PASS invalid price range")


def nonce(html):
    match = re.search(r'name="estate_nonce" value="([^"]+)"', html)
    assert match, "Form nonce is missing"
    return match.group(1)


_, html = request("/contact/")
url, _ = request("/wp-admin/admin-post.php", {
    "action": "estate_contact", "estate_nonce": nonce(html),
    "name": "Test Buyer", "email": "buyer@example.test", "message": "Please contact me.",
})
assert "contact=sent" in url, url
print("PASS contact form")

_, html = request("/properties/makati-garden-residence/")
property_id = re.search(r'name="property_id" value="(\d+)"', html)
assert property_id, "Viewing form property ID is missing"
url, _ = request("/wp-admin/admin-post.php", {
    "action": "estate_viewing", "estate_nonce": nonce(html), "property_id": property_id.group(1),
    "name": "Test Buyer", "email": "buyer@example.test", "message": "Saturday afternoon, please.",
})
assert "viewing=sent" in url, url
print("PASS viewing form")

try:
    request("/wp-admin/admin-post.php", {
        "action": "estate_viewing", "estate_nonce": "bad", "property_id": property_id.group(1),
        "name": "Test Buyer", "email": "buyer@example.test",
    })
except HTTPError as error:
    assert error.code == 403
else:
    raise AssertionError("Invalid form nonce was accepted")
print("PASS invalid nonce")
