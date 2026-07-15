#!/usr/bin/env python3
"""Phase 6 public-site quality gate for the isolated local WordPress instance."""

from __future__ import annotations

import json
import os
import re
import sys
from collections import deque
from dataclasses import dataclass
from urllib.parse import urldefrag, urljoin, urlparse

import requests
from bs4 import BeautifulSoup

BASE = os.environ.get("SITE_BASE", "http://127.0.0.1:8080/")
MAX_PAGES = 100
TIMEOUT = 10
FORBIDDEN_VISIBLE_PATTERNS = (
    re.compile(r"[\u4e00-\u9fff]+(?:公司|预约|机场|服务|客户|流程|费用|详细|确认)"),
    re.compile(r"Lorem ipsum", re.I),
    re.compile(r"TODO|TBD|FIXME", re.I),
)
EXPECTED_DRAFT_PATHS = frozenset(
    {
        "/cases/",
        "/company/",
        "/contact/",
        "/privacy-policy/",
        "/terms/",
        "/cancellation-policy/",
        "/legal/",
    }
)


@dataclass
class Failure:
    url: str
    message: str


def local_public_url(url: str) -> bool:
    parsed = urlparse(url)
    return parsed.netloc == urlparse(BASE).netloc and not parsed.path.startswith(("/wp-admin", "/wp-json", "/wp-login.php"))


def normalize(url: str) -> str:
    clean, _fragment = urldefrag(url)
    parsed = urlparse(clean)
    path = parsed.path or "/"
    query = parsed.query if parsed.path == "/" and parsed.query.startswith("s=") else ""
    return parsed._replace(path=path, query=query, fragment="").geturl()


def visible_text(soup: BeautifulSoup) -> str:
    for node in soup(["script", "style", "template", "noscript"]):
        node.decompose()
    return " ".join(soup.stripped_strings)


def add_failure(failures: list[Failure], url: str, message: str) -> None:
    failures.append(Failure(url=url, message=message))


def validate_page(
    url: str,
    response: requests.Response,
    failures: list[Failure],
    release_blockers: list[Failure],
) -> list[str]:
    if response.status_code != 200:
        path = urlparse(url).path
        if response.status_code == 404 and path in EXPECTED_DRAFT_PATHS:
            release_blockers.append(Failure(url=url, message="approved draft remains unpublished pending verified business/legal content"))
        else:
            add_failure(failures, url, f"HTTP {response.status_code}")
        return []

    content_type = response.headers.get("content-type", "")
    if "text/html" not in content_type:
        add_failure(failures, url, f"unexpected content type: {content_type}")
        return []

    soup = BeautifulSoup(response.text, "html.parser")
    html = soup.find("html")
    if html is None or not str(html.get("lang", "")).lower().startswith("ja"):
        add_failure(failures, url, "html lang must be Japanese")

    title = soup.find("title")
    if title is None or not title.get_text(strip=True):
        add_failure(failures, url, "missing document title")

    if "?s=" not in url:
        descriptions = soup.select('meta[name="description"]')
        if len(descriptions) != 1 or not descriptions[0].get("content", "").strip():
            add_failure(failures, url, f"expected one non-empty description, got {len(descriptions)}")
        canonicals = soup.select('link[rel="canonical"]')
        if len(canonicals) != 1:
            add_failure(failures, url, f"expected one canonical, got {len(canonicals)}")
        elif normalize(url) != normalize(urljoin(url, canonicals[0].get("href", ""))):
            add_failure(failures, url, "canonical does not match the clean page URL")

    h1s = soup.find_all("h1")
    if len(h1s) != 1:
        add_failure(failures, url, f"expected one H1, got {len(h1s)}")

    main = soup.find("main")
    if main is None or main.get("id") != "main-content":
        add_failure(failures, url, "missing #main-content landmark")

    for image in soup.find_all("img"):
        if image.get("alt") is None:
            add_failure(failures, url, "image without alt attribute")

    text = visible_text(BeautifulSoup(response.text, "html.parser"))
    for pattern in FORBIDDEN_VISIBLE_PATTERNS:
        match = pattern.search(text)
        if match:
            add_failure(failures, url, f"forbidden placeholder/non-Japanese phrase: {match.group(0)}")

    schemas = []
    for script in soup.select('script[type="application/ld+json"]'):
        try:
            schemas.append(json.loads(script.string or "{}"))
        except json.JSONDecodeError as error:
            add_failure(failures, url, f"invalid JSON-LD: {error}")
    if not schemas:
        add_failure(failures, url, "missing JSON-LD")

    if urlparse(url).path == "/faq/":
        serialized = json.dumps(schemas, ensure_ascii=False)
        if "FAQPage" not in serialized:
            add_failure(failures, url, "FAQ page is missing FAQPage schema")
        visible_questions = len(soup.select(".jat-faq details"))
        schema_questions = serialized.count('"Question"')
        if visible_questions != schema_questions:
            add_failure(failures, url, f"FAQ schema/visible count mismatch: {schema_questions}/{visible_questions}")

    if urlparse(url).path == "/reservation/":
        form = soup.select_one("form.jat-reservation__form")
        if form is None:
            add_failure(failures, url, "missing reservation form")
        else:
            for field in form.select("input:not([type=hidden]), select, textarea"):
                field_id = field.get("id")
                explicit_label = bool(field_id and soup.select_one(f'label[for="{field_id}"]'))
                nested_label = field.find_parent("label") is not None
                if not explicit_label and not nested_label:
                    add_failure(failures, url, f"unlabelled reservation field: {field.get('name', '?')}")
            if soup.select_one('.jat-reservation [role="alert"][aria-live]') is None:
                add_failure(failures, url, "reservation form lacks accessible error summary")

    discovered: list[str] = []
    for anchor in soup.find_all("a", href=True):
        href = str(anchor["href"]).strip()
        if not href or href.startswith(("mailto:", "tel:", "javascript:")):
            continue
        absolute = normalize(urljoin(url, href))
        if local_public_url(absolute):
            discovered.append(absolute)
    return discovered


def main() -> int:
    session = requests.Session()
    session.headers["User-Agent"] = "JAT-Phase6-Quality-Gate/1.0"
    queue: deque[str] = deque([BASE])
    visited: set[str] = set()
    failures: list[Failure] = []
    release_blockers: list[Failure] = []

    while queue and len(visited) < MAX_PAGES:
        url = normalize(queue.popleft())
        if url in visited or not local_public_url(url):
            continue
        visited.add(url)
        try:
            response = session.get(url, timeout=TIMEOUT, allow_redirects=True)
        except requests.RequestException as error:
            add_failure(failures, url, f"request failed: {error}")
            continue
        for discovered in validate_page(url, response, failures, release_blockers):
            if discovered not in visited:
                queue.append(discovered)

    required = ("/service/", "/flow/", "/area/", "/faq/", "/reservation/")
    paths = {urlparse(url).path for url in visited}
    for path in required:
        if path not in paths:
            add_failure(failures, BASE, f"required page not reached: {path}")

    print(f"Crawled {len(visited)} public pages")
    for blocker in release_blockers:
        print(f"BLOCKED {blocker.url}: {blocker.message}")
    if failures:
        for failure in failures:
            print(f"FAIL {failure.url}: {failure.message}")
        print(f"Phase 6 site-quality gate failed with {len(failures)} issue(s)")
        return 1

    print("Phase 6 site-quality gate passed")
    if release_blockers:
        print(f"Release readiness remains blocked by {len(release_blockers)} approved draft page(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
