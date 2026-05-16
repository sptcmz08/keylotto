#!/usr/bin/env python3
"""
ponhuay24.com Backup Scraper
ดึงผลหวยจาก ponhuay24.com

Usage:
  .venv/bin/python scripts/scrape_ponhuay24.py
  .venv/bin/python scripts/scrape_ponhuay24.py --debug
  .venv/bin/python scripts/scrape_ponhuay24.py --slug lao-tai
"""

import sys
import re
import json
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from datetime import datetime

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8')

# Pages to scrape — ponhuay24 has clean separate pages
SCRAPE_PAGES = [
    'https://ponhuay24.com/app/lottolaos',
    'https://ponhuay24.com/app/lottohanoi',
    'https://ponhuay24.com/app/lottovip',
    'https://ponhuay24.com/app/lottostock',
    'https://ponhuay24.com/app/lottoother',
    'https://ponhuay24.com/',
]

TARGET_SLUG_PAGES = {
    'lao-pattana': ['https://ponhuay24.com/app/lottolaos/'],
}

# Name-to-slug mapping for ponhuay24 (names WITHOUT spaces)
NAME_MAPPINGS = {
    # === ลาว ===
    'ลาวใต้': 'lao-tai',
    'ลาวประตูชัย': 'lao-pratuchai',
    'ลาวสันติภาพ': 'lao-santiphap',
    'ลาวกาชาด': 'lao-redcross',
    'ลาวEXTRA': 'lao-extra',
    'ลาว Extra': 'lao-extra',
    'หวยลาวTV': 'lao-tv',
    'ลาวทีวี': 'lao-tv',
    'หวยลาวHD': 'lao-hd',
    'ลาว HD': 'lao-hd',
    'ลาวSTAR': 'lao-star',
    'ลาวสตาร์': 'lao-star',
    'ลาวสตาร์VIP': 'lao-star-vip',
    'ลาวสามัคคีVIP': 'lao-samakki-vip',
    'ลาวสามัคคี': 'lao-samakki',
    'หวยลาวVIP': 'lao-vip',
    'ลาว VIP': 'lao-vip',
    'ลาวอาเซียน': 'lao-asean',
    'หวยลาวพัฒนา': 'lao-pattana',
    'หวยลาวพัฒนา20.30': 'lao-pattana',
    'ลาวพัฒนา20.30': 'lao-pattana',
    'ลาวพัฒนา': 'lao-pattana',
    'ประชาชนลาว': 'lao-prachachon',
    'ลาวประชาคม': 'lao-prachachon',

    # === ฮานอย ===
    'ฮานอยปกติ': 'hanoi',
    'ฮานอยVIP': 'hanoi-vip',
    'ฮานอยพิเศษ': 'hanoi-special',
    'ฮานอยสามัคคี': 'hanoi-samakki',
    'ฮานอยพัฒนา': 'hanoi-pattana',
    'ฮานอยEXTRA': 'hanoi-extra',
    'ฮานอย Extra': 'hanoi-extra',
    'ฮานอยกาชาด': 'hanoi-redcross',
    'ฮานอยอาเซียน': 'hanoi-asean',
    'ฮานอยSTAR': 'hanoi-star',
    'ฮานอยสตาร์': 'hanoi-star',
    'หวยฮานอยHD': 'hanoi-hd',
    'ฮานอย HD': 'hanoi-hd',
    'หวยฮานอยTV': 'hanoi-tv',
    'ฮานอยทีวี': 'hanoi-tv',
    'ฮานอยเฉพาะกิจ': 'hanoi-adhoc',
    'ฮานอยตรุษจีน': 'hanoi-chinese-ny',
    'ฮานอย ตรุจีน': 'hanoi-chinese-ny',
    'ฮานอยตรุจีน': 'hanoi-chinese-ny',

    # === หุ้น ===
    # ดาวโจนส์/STAR/VIP → ออกผลดึก (00:00+) ใช้ exphuay เท่านั้น
    'หุ้นนิเคอิเช้าVIP': 'nikkei-morning-vip',
    'หุ้นนิเคอิบ่ายVIP': 'nikkei-afternoon-vip',
    'หุ้นจีนเช้าVIP': 'china-morning-vip',
    'หุ้นจีนบ่ายVIP': 'china-afternoon-vip',
    'หุ้นฮั่งเส็งเช้าVIP': 'hangseng-morning-vip',
    'ฮั่งเส็งบ่ายVIP': 'hangseng-afternoon-vip',
    'หุ้นไต้หวันVIP': 'taiwan-vip',
    'หุ้นเกาหลีVIP': 'korea-vip',
    'หุ้นสิงคโปร์VIP': 'singapore-vip',
    # หุ้นอังกฤษ/เยอรมัน/รัสเซีย/ดาวโจนส์ VIP → ออกผลดึก (23:00+) ใช้ exphuay เท่านั้น
    # ponhuay24 ไม่มี date/time validation → ดึงผลเก่ามาเป็นวันนี้
    'หุ้นจีนเช้า': 'china-morning',
    'หุ้นจีนบ่าย': 'china-afternoon',
    'หุ้นฮั่งเส็งเช้า': 'hangseng-morning',
    'ฮั่งเส็งเช้า': 'hangseng-morning',
    'ฮั่งเส็งบ่าย': 'hangseng-afternoon',
    'หุ้นไต้หวัน': 'taiwan',
    'หุ้นเกาหลี': 'korea',
    'หุ้นสิงคโปร์': 'singapore',
    'หุ้นอินเดีย': 'india',
    # หุ้นอังกฤษ/เยอรมัน/รัสเซีย → ลบออก (ออก 23:00+ ดึงจาก exphuay อย่างเดียว)

    # === อื่นๆ ===
    'หวย 12 ราศี': 'rasi-12',
    '12ราศี': 'rasi-12',
    'หุ้นอียิปต์': 'egypt',
    'มาเลย์': 'malay',
    'หวยมาเลย์': 'malay',
}

# Slugs we really need from ponhuay24
PRIORITY_SLUGS = {
    'lao-pattana',
    'lao-tai', 'lao-pratuchai', 'lao-santiphap',
    'dowjones-star', 'hanoi-chinese-ny', 'rasi-12',
    'nikkei-morning', 'nikkei-afternoon',
    'egypt', 'malay',
}


def normalize_lottery_name(name):
    normalized = re.sub(r'\s+', '', name.strip())
    normalized = normalized.replace('เวลา', '')
    normalized = normalized.replace('น.', '')
    normalized = normalized.replace(':', '.')
    return normalized


NORMALIZED_NAME_MAPPINGS = {
    normalize_lottery_name(name): slug
    for name, slug in NAME_MAPPINGS.items()
}

TARGET_SLUG_NAME_ALIASES = {
    slug: [
        mapped_name
        for mapped_name, mapped_slug in NORMALIZED_NAME_MAPPINGS.items()
        if mapped_slug == slug
    ]
    for slug in set(NAME_MAPPINGS.values())
}


def parse_ponhuay24_line(line):
    """
    Parse a ponhuay24 table line:
    'HH:MM:SS   ชื่อหวย    XXX    XX    คาดการ ( X , X )'
    Returns (name, three_top, two_bottom) or None
    """
    # Pattern: time, then Thai name, then 3-digit number, then 2-digit number
    m = re.match(
        r'\d{2}:\d{2}:\d{2}\s+'  # time
        r'(.+?)\s+'               # lottery name (greedy)
        r'(\d{3})\s+'             # 3-digit top
        r'(\d{2})\s+'             # 2-digit bottom
        r'คาดการ',                # followed by "คาดการ"
        line.strip()
    )
    if m:
        name = m.group(1).strip()
        three_top = m.group(2)
        two_bottom = m.group(3)
        return name, three_top, two_bottom

    # Also try pattern without คาดการ (some lines may differ)
    m2 = re.match(
        r'\d{2}:\d{2}:\d{2}\s+'
        r'(.+?)\s+'
        r'(\d{3})\s+'
        r'(\d{2})',
        line.strip()
    )
    if m2:
        name = m2.group(1).strip()
        three_top = m2.group(2)
        two_bottom = m2.group(3)
        return name, three_top, two_bottom

    return None


def parse_ponhuay24_cells(cells):
    """Parse one DOM table row where ponhuay24 keeps each value in a separate cell."""
    cleaned = [
        re.sub(r'\s+', ' ', str(cell).strip())
        for cell in cells
        if str(cell).strip()
    ]
    if len(cleaned) < 4:
        return None

    time_index = None
    for i, cell in enumerate(cleaned):
        if re.fullmatch(r'\d{2}:\d{2}:\d{2}', cell):
            time_index = i
            break

    if time_index is None:
        return None

    result_index = None
    for i in range(time_index + 1, len(cleaned)):
        if re.fullmatch(r'\d{3}|XXX', cleaned[i], flags=re.IGNORECASE):
            result_index = i
            break

    if result_index is None:
        return None

    bottom_index = None
    for i in range(result_index + 1, len(cleaned)):
        if re.fullmatch(r'\d{2}|XX', cleaned[i], flags=re.IGNORECASE):
            bottom_index = i
            break

    if bottom_index is None:
        return None

    name = ' '.join(cleaned[time_index + 1:result_index]).strip()
    name = re.sub(r'\s+', ' ', name)
    if not name:
        return None

    return name, cleaned[result_index], cleaned[bottom_index]


def resolve_slug_for_name(name, target_slug=None, debug=False):
    normalized_name = normalize_lottery_name(name)

    if target_slug:
        target_aliases = TARGET_SLUG_NAME_ALIASES.get(target_slug, [])
        if target_aliases and not any(alias and alias in normalized_name for alias in target_aliases):
            return None

    slug = NAME_MAPPINGS.get(name)
    if not slug:
        slug = NORMALIZED_NAME_MAPPINGS.get(normalized_name)

    if not slug:
        for mapped_name, mapped_slug in NORMALIZED_NAME_MAPPINGS.items():
            if mapped_name and mapped_name in normalized_name:
                slug = mapped_slug
                break

    if not slug and debug:
        print(f'[Ponhuay24]   ⚠️ No mapping: "{name}"', file=sys.stderr)

    if target_slug and slug != target_slug:
        return None

    return slug


def build_result(slug, name, three_top, two_bottom, today, four_top=''):
    four_top = str(four_top or '').strip()
    return {
        'slug': slug,
        'lottery_name': name,
        'first_prize': four_top or three_top,
        'four_top': four_top,
        'three_top': three_top,
        'two_top': three_top[-2:],
        'two_bottom': two_bottom,
        'draw_date': today,
        'source': 'ponhuay24.com',
    }


def parse_ponhuay24_line_windows(lines, target_slug=None, debug=False):
    """Parse text where each table cell is split onto its own line."""
    results = []
    found_slugs = set()
    cleaned = [
        re.sub(r'\s+', ' ', str(line).strip())
        for line in lines
        if str(line).strip()
    ]

    for i, line in enumerate(cleaned):
        normalized_line = normalize_lottery_name(line)
        matched_slug = None
        matched_name = line

        for alias, slug in NORMALIZED_NAME_MAPPINGS.items():
            if not alias or alias not in normalized_line:
                continue
            if target_slug and slug != target_slug:
                continue
            matched_slug = slug
            matched_name = line
            break

        if not matched_slug or matched_slug in found_slugs:
            continue

        numbers = []
        for value in cleaned[i + 1:i + 10]:
            upper_value = value.upper()
            if re.fullmatch(r'\d{3}|XXX', upper_value):
                numbers.append(upper_value)
                continue
            if re.fullmatch(r'\d{2}|XX', upper_value):
                numbers.append(upper_value)
                continue
            if value.startswith('คาดการ') or value in ['ออกผลแล้ว', 'กำลังออกผล', 'รอผล', 'ปิด']:
                break

        three_top = None
        two_bottom = None
        for value in numbers:
            if three_top is None and re.fullmatch(r'\d{3}|XXX', value):
                three_top = value
                continue
            if three_top is not None and re.fullmatch(r'\d{2}|XX', value):
                two_bottom = value
                break

        if not three_top or not two_bottom:
            if debug:
                sample = ' | '.join(cleaned[max(0, i - 2):i + 10])
                print(f'[Ponhuay24]   ⚠️ Incomplete split-row parse near "{line}": {sample}', file=sys.stderr)
            continue

        if three_top == 'XXX' or two_bottom == 'XX':
            continue

        found_slugs.add(matched_slug)
        results.append((matched_slug, matched_name, three_top, two_bottom))

    return results


def http_json(url):
    req = Request(
        url,
        headers={
            'content-type': 'application/x-www-form-urlencoded',
            'appVersion': '1.1.0',
            'User-Agent': 'Mozilla/5.0',
        },
    )
    with urlopen(req, timeout=30) as res:
        return json.loads(res.read().decode('utf-8'))


def scrape_ponhuay24_api(debug=False, target_slug=None):
    """Use the same JSON endpoints as the Nuxt page before falling back to browser scraping."""
    today = datetime.now().strftime('%Y-%m-%d')
    results = []
    found_slugs = set()

    try:
        award_list = http_json('https://app.all123th.com/get-awards/list')
    except Exception as e:
        if debug:
            print(f'[Ponhuay24] ⚠️ API list failed: {e}', file=sys.stderr)
        return []

    for row in award_list if isinstance(award_list, list) else []:
        name = str(row.get('name') or '').strip()
        slug = resolve_slug_for_name(name, target_slug=target_slug, debug=False)
        if not slug or slug in found_slugs:
            continue

        if target_slug and slug != target_slug:
            continue

        award_id = str(row.get('id') or '').strip()
        draw_date = str(row.get('date') or row.get('lotto_date') or today).strip() or today
        if not award_id:
            continue

        try:
            params = urlencode({'date': draw_date})
            award = http_json(f'https://app.all123th.com/get-awards/id/{award_id}?{params}')
        except Exception as e:
            if debug:
                print(f'[Ponhuay24] ⚠️ API award failed for {name} ({award_id}): {e}', file=sys.stderr)
            continue

        three_top = str(award.get('digit3_top') or '').strip()
        two_bottom = str(award.get('digit2_bottom') or '').strip()
        four_top = str(award.get('digit4') or '').strip()
        award_date = str(award.get('date') or draw_date).strip() or draw_date

        if not re.fullmatch(r'\d{3}', three_top) or not re.fullmatch(r'\d{2}', two_bottom):
            if debug:
                print(f'[Ponhuay24] ⏭️ API placeholder {name}: {three_top}/{two_bottom}', file=sys.stderr)
            continue

        if four_top and not re.fullmatch(r'\d{4}', four_top):
            four_top = ''

        found_slugs.add(slug)
        result = build_result(slug, name, three_top, two_bottom, award_date, four_top=four_top)
        results.append(result)

        if slug in PRIORITY_SLUGS:
            four_part = f' 4ตัว={four_top}' if four_top else ''
            print(f'[Ponhuay24]   🎯 API {name}: {three_top} / {two_bottom}{four_part} → {slug} ★', file=sys.stderr)
        elif debug:
            print(f'[Ponhuay24]   ✅ API {name}: {three_top} / {two_bottom} → {slug}', file=sys.stderr)

    return results


def scrape_ponhuay24(debug=False, target_slug=None):
    """Main scraper — returns JSON results"""
    print('[Ponhuay24] Starting Camoufox scraper...', file=sys.stderr)

    api_results = scrape_ponhuay24_api(debug=debug, target_slug=target_slug)
    if api_results:
        print(f'[Ponhuay24] 📊 API TOTAL: {len(api_results)} results', file=sys.stderr)
        return {
            'success': True,
            'results': api_results,
            'scraped_at': datetime.now().isoformat(),
        }

    print('[Ponhuay24] API returned no target results, falling back to Camoufox...', file=sys.stderr)

    try:
        from camoufox.sync_api import Camoufox
    except ImportError:
        print('[Ponhuay24] ERROR: camoufox not installed', file=sys.stderr)
        return {'success': False, 'error': 'camoufox not installed', 'results': []}

    today = datetime.now().strftime('%Y-%m-%d')
    all_results = []
    found_slugs = set()

    try:
        with Camoufox(headless=True) as browser:
            page = browser.new_page()

            urls = TARGET_SLUG_PAGES.get(target_slug, SCRAPE_PAGES)
            for url in urls:
                try:
                    print(f'[Ponhuay24] 🌐 Loading {url}...', file=sys.stderr)
                    page.goto(url, timeout=30000, wait_until='domcontentloaded')
                    page.wait_for_timeout(5000)

                    # Wait for SPA content
                    try:
                        page.wait_for_selector('table, .ponhuay, .trr', timeout=10000)
                    except Exception:
                        pass
                    page.wait_for_timeout(2000)

                    text = page.evaluate('() => document.body ? document.body.innerText : ""')
                    if not text or len(text) < 100:
                        print(f'[Ponhuay24] ⚠️ Page too short', file=sys.stderr)
                        continue

                    lines = text.split('\n')
                    page_count = 0

                    table_rows = page.evaluate('''() => Array.from(document.querySelectorAll('tr'))
                        .map((tr) => Array.from(tr.querySelectorAll('th,td'))
                            .map((td) => (td.innerText || td.textContent || '').trim())
                            .filter(Boolean))
                        .filter((row) => row.length >= 4)
                    ''')
                    for cells in table_rows:
                        parsed = parse_ponhuay24_cells(cells)
                        if not parsed:
                            continue

                        name, three_top, two_bottom = parsed

                        # Skip XXX placeholder results
                        if three_top.upper() == 'XXX' or two_bottom.upper() == 'XX':
                            continue

                        slug = resolve_slug_for_name(name, target_slug=target_slug, debug=debug)
                        if not slug or slug in found_slugs:
                            continue

                        found_slugs.add(slug)
                        page_count += 1
                        all_results.append(build_result(slug, name, three_top, two_bottom, today))

                        if slug in PRIORITY_SLUGS:
                            print(f'[Ponhuay24]   🎯 {name}: {three_top} / {two_bottom} → {slug} ★', file=sys.stderr)
                        elif debug:
                            print(f'[Ponhuay24]   ✅ {name}: {three_top} / {two_bottom} → {slug}', file=sys.stderr)

                    for slug, name, three_top, two_bottom in parse_ponhuay24_line_windows(lines, target_slug=target_slug, debug=debug):
                        if slug in found_slugs:
                            continue

                        found_slugs.add(slug)
                        page_count += 1
                        all_results.append(build_result(slug, name, three_top, two_bottom, today))

                        if slug in PRIORITY_SLUGS:
                            print(f'[Ponhuay24]   🎯 {name}: {three_top} / {two_bottom} → {slug} ★', file=sys.stderr)
                        elif debug:
                            print(f'[Ponhuay24]   ✅ {name}: {three_top} / {two_bottom} → {slug}', file=sys.stderr)

                    for line in lines:
                        line = line.strip()
                        if not line:
                            continue

                        parsed = parse_ponhuay24_line(line)
                        if not parsed:
                            continue

                        name, three_top, two_bottom = parsed

                        # Skip XXX placeholder results
                        if three_top.upper() == 'XXX' or two_bottom.upper() == 'XX':
                            continue

                        slug = resolve_slug_for_name(name, target_slug=target_slug, debug=debug)
                        if not slug or slug in found_slugs:
                            continue


                        found_slugs.add(slug)
                        page_count += 1

                        all_results.append(build_result(slug, name, three_top, two_bottom, today))

                        if slug in PRIORITY_SLUGS:
                            print(f'[Ponhuay24]   🎯 {name}: {three_top} / {two_bottom} → {slug} ★', file=sys.stderr)
                        elif debug:
                            print(f'[Ponhuay24]   ✅ {name}: {three_top} / {two_bottom} → {slug}', file=sys.stderr)

                    print(f'[Ponhuay24] 📊 {page_count} results from this page', file=sys.stderr)

                except Exception as e:
                    print(f'[Ponhuay24] ❌ Error: {str(e)[:120]}', file=sys.stderr)
                    continue

            # Filter by target
            if target_slug:
                all_results = [r for r in all_results if r['slug'] == target_slug]

            # Summary
            priority_found = [r['slug'] for r in all_results if r['slug'] in PRIORITY_SLUGS]
            if target_slug:
                priority_missing = {target_slug} - set(priority_found)
            else:
                priority_missing = PRIORITY_SLUGS - set(priority_found)
            print(f'\n[Ponhuay24] 📊 TOTAL: {len(all_results)} results', file=sys.stderr)
            if priority_found:
                print(f'[Ponhuay24] 🎯 Priority found ({len(priority_found)}): {", ".join(sorted(priority_found))}', file=sys.stderr)
            if priority_missing:
                print(f'[Ponhuay24] ❌ Priority missing ({len(priority_missing)}): {", ".join(sorted(priority_missing))}', file=sys.stderr)

            return {
                'success': True,
                'results': all_results,
                'scraped_at': datetime.now().isoformat(),
            }

    except Exception as e:
        print(f'[Ponhuay24] ❌ Fatal error: {str(e)[:200]}', file=sys.stderr)
        return {'success': False, 'error': str(e)[:200], 'results': []}


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('--debug', action='store_true')
    parser.add_argument('--slug', type=str, default=None)
    args = parser.parse_args()

    result = scrape_ponhuay24(debug=args.debug, target_slug=args.slug)
    print(json.dumps(result, ensure_ascii=False, indent=2))
