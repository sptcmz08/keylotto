#!/usr/bin/env python3
"""
ponhuay24.com Backup Scraper
ดึงผลหวยจาก ponhuay24.com สำหรับตัวที่ raakaadee ดึงไม่ได้

Usage:
  .venv/bin/python scripts/scrape_ponhuay24.py
  .venv/bin/python scripts/scrape_ponhuay24.py --debug
  .venv/bin/python scripts/scrape_ponhuay24.py --slug lao-tai
"""

import sys
import re
import json
from datetime import datetime

# Pages to scrape — ponhuay24 has clean separate pages
SCRAPE_PAGES = [
    'https://ponhuay24.com/app/lottolaos',
    'https://ponhuay24.com/app/lottohanoi',
    'https://ponhuay24.com/app/lottovip',
    'https://ponhuay24.com/app/lottostock',
    'https://ponhuay24.com/app/lottoother',
    'https://ponhuay24.com/',
]

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

# Slugs we really need from ponhuay24 (ones raakaadee can't get)
PRIORITY_SLUGS = {
    'lao-tai', 'lao-pratuchai', 'lao-santiphap',
    'dowjones-star', 'hanoi-chinese-ny', 'rasi-12',
    'nikkei-morning', 'nikkei-afternoon',
    'egypt', 'malay',
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


def scrape_ponhuay24(debug=False, target_slug=None):
    """Main scraper — returns JSON results"""
    print('[Ponhuay24] Starting Camoufox scraper...', file=sys.stderr)

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

            for url in SCRAPE_PAGES:
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

                    for line in lines:
                        line = line.strip()
                        if not line:
                            continue

                        parsed = parse_ponhuay24_line(line)
                        if not parsed:
                            continue

                        name, three_top, two_bottom = parsed

                        # Skip XXX placeholder results
                        if three_top == 'XXX' or two_bottom == 'XX':
                            continue

                        # Match to slug
                        slug = NAME_MAPPINGS.get(name)
                        if not slug:
                            # Try fuzzy match (remove spaces)
                            name_nospace = name.replace(' ', '')
                            slug = NAME_MAPPINGS.get(name_nospace)

                        if not slug:
                            if debug:
                                print(f'[Ponhuay24]   ⚠️ No mapping: "{name}"', file=sys.stderr)
                            continue

                        if slug in found_slugs:
                            continue

                        # Filter by target if specified
                        if target_slug and slug != target_slug:
                            continue

                        found_slugs.add(slug)
                        page_count += 1

                        result = {
                            'slug': slug,
                            'lottery_name': name,
                            'first_prize': three_top,
                            'three_top': three_top,
                            'two_top': three_top[-2:],
                            'two_bottom': two_bottom,
                            'draw_date': today,
                            'source': 'ponhuay24.com',
                        }
                        all_results.append(result)

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
