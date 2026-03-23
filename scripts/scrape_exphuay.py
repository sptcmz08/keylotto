#!/usr/bin/env python3
"""
ExpHuay.com Lottery Scraper — ดึงผลหวยทุกประเภทจาก exphuay.com
ใช้ HTTP request ธรรมดา — ไม่ต้องเปิด browser (ประหยัด RAM, เร็วมาก)

ดึงจากหน้า backward (สถิติย้อนหลัง) ซึ่งมี format:
  <a href="...?date=2026-03-19">...</a> <span>399</span> <span>54</span>

Usage: python scripts/scrape_exphuay.py [--date=2026-03-21] [--debug]
Output: JSON to stdout
"""

import sys
import json
import re
import argparse
from datetime import datetime, date
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError

# ============================================
# ExpHuay slug → Key DB slug mapping
# ============================================
EXPHUAY_LOTTERIES = {
    # === หุ้นปกติ ===
    'dji':              'dowjones',
    'nikkei-morning':   'nikkei-morning',
    'nikkei-afternoon': 'nikkei-afternoon',
    'szse-morning':     'china-morning',
    'szse-afternoon':   'china-afternoon',
    'hsi-morning':      'hangseng-morning',
    'hsi-afternoon':    'hangseng-afternoon',
    'twse':             'taiwan',
    'ktop30':           'korea',
    'sgx':              'singapore',
    'set':              'thai-stock',
    'bsesn':            'india',
    'egx30':            'egypt',
    'moexbc':           'russia',
    'gdaxi':            'germany',
    'ftse100':          'uk',

    # === หุ้น VIP ===
    'dowjones-vip':         'dowjones-vip',
    'nikkei-vip-morning':   'nikkei-morning-vip',
    'nikkei-vip-afternoon': 'nikkei-afternoon-vip',
    'szse-vip-morning':     'china-morning-vip',
    'szse-vip-afternoon':   'china-afternoon-vip',
    'hsi-vip-morning':      'hangseng-morning-vip',
    'hsi-vip-afternoon':    'hangseng-afternoon-vip',
    'twse-vip':             'taiwan-vip',
    'ktop30-vip':           'korea-vip',
    'sgx-vip':              'singapore-vip',
    'england-vip':          'uk-vip',
    'germany-vip':          'germany-vip',
    'russia-vip':           'russia-vip',

    # === ฮานอย ===
    'minhngoc':         'hanoi',
    'xsthm':            'hanoi-special',
    'mlnhngo':          'hanoi-vip',
    'xosoredcross':     'hanoi-redcross',
    'hanoiasean':       'hanoi-asean',
    'xosohd':           'hanoi-hd',
    'minhngoctv':       'hanoi-tv',
    'minhngocstar':     'hanoi-star',
    'xosounion':        'hanoi-samakki',
    'xosodevelop':      'hanoi-pattana',
    'xosoextra':        'hanoi-extra',

    # === ลาว ===
    'laopatuxay':       'lao-pratuchai',
    'laosantipap':      'lao-santiphap',
    'laocitizen':       'lao-prachachon',
    'laoextra':         'lao-extra',
    'laotv':            'lao-tv',
    'laoshd':           'lao-hd',
    'laostars':         'lao-star',
    'laostarsvip':      'lao-star-vip',
    'laounion':         'lao-samakki',
    'laounionvip':      'lao-samakki-vip',
    'laosvip':          'lao-vip',
    'laosasean':        'lao-asean',
    'laoredcross':      'lao-redcross',
    'laopattanamidday': 'lao-pattana',

    # === ดาวโจนส์ ===
    'dowjonestar':      'dowjones-star',
}

BACKWARD_BASE = 'https://exphuay.com/backward'

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language': 'th,en;q=0.5',
}


def fetch_page(url, debug=False):
    """Fetch HTML content from URL"""
    try:
        req = Request(url, headers=HEADERS)
        with urlopen(req, timeout=15) as response:
            html = response.read().decode('utf-8')
            if debug:
                print(f'[ExpHuay]   Fetched {len(html)} bytes', file=sys.stderr)
            return html
    except (URLError, HTTPError) as e:
        if debug:
            print(f'[ExpHuay]   ❌ Error: {e}', file=sys.stderr)
        return None


def parse_backward_page(html, target_date, exphuay_slug, key_slug, debug=False):
    """Parse result from backward page for a specific date"""
    if not html:
        return None

    date_pos = html.find(f'date={target_date}')
    if date_pos == -1:
        return None

    chunk = html[date_pos:date_pos + 500]

    # Check for "งดออกผล" (no draw / holiday)
    if 'งดออกผล' in chunk:
        if debug:
            print(f'[ExpHuay]   ⏭️ งดออกผล (วันหยุด)', file=sys.stderr)
        return 'NO_DRAW'

    # Strategy 1: Numbers inside HTML tags >399< >54<
    numbers = re.findall(r'>(\d{2,3})<', chunk)
    if len(numbers) >= 2:
        three_top = None
        two_bot = None
        for n in numbers:
            if len(n) == 3 and three_top is None:
                three_top = n
            elif len(n) == 2 and two_bot is None and three_top is not None:
                two_bot = n
            if three_top and two_bot:
                break

        if three_top and two_bot:
            return {
                'slug': key_slug,
                'three_top': three_top,
                'two_top': three_top[-2:],
                'two_bottom': two_bot,
                'draw_date': target_date,
                'source': 'exphuay.com',
            }

    # Strategy 2: Plain text 399 54
    m = re.search(r'(\d{3})\s+(\d{2})', chunk)
    if m:
        return {
            'slug': key_slug,
            'three_top': m.group(1),
            'two_top': m.group(1)[-2:],
            'two_bottom': m.group(2),
            'draw_date': target_date,
            'source': 'exphuay.com',
        }

    return None


def scrape_exphuay(target_date=None, debug=False):
    """Main scraper — fetch all lotteries from ExpHuay"""
    if not target_date:
        target_date = date.today().strftime('%Y-%m-%d')

    results = []
    skipped = 0
    failed = 0

    for exphuay_slug, key_slug in EXPHUAY_LOTTERIES.items():
        url = f'{BACKWARD_BASE}/{exphuay_slug}'
        if debug:
            print(f'[ExpHuay] 🌐 {exphuay_slug} → {key_slug}...', file=sys.stderr)

        html = fetch_page(url, debug)
        result = parse_backward_page(html, target_date, exphuay_slug, key_slug, debug)

        if result and result != 'NO_DRAW':
            if debug:
                print(f'[ExpHuay]   ✅ {result["three_top"]} / {result["two_top"]} / {result["two_bottom"]}', file=sys.stderr)
            results.append(result)
        elif result == 'NO_DRAW':
            skipped += 1
        else:
            if html and f'date={target_date}' not in html:
                skipped += 1
                if debug:
                    print(f'[ExpHuay]   ⏭️ ไม่มีผลวันที่ {target_date}', file=sys.stderr)
            else:
                failed += 1
                if debug:
                    print(f'[ExpHuay]   ❌ Parse failed', file=sys.stderr)

    if debug:
        print(f'\n[ExpHuay] 📊 Done: ✅ {len(results)} ได้ผล, ⏭️ {skipped} ข้าม, ❌ {failed} ล้มเหลว', file=sys.stderr)

    return {
        'success': len(results) > 0,
        'results': results,
        'total': len(results),
        'scraped_at': datetime.now().isoformat(),
        'source': 'exphuay.com',
    }


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='ExpHuay.com Lottery Scraper')
    parser.add_argument('--date', type=str, help='Target date (YYYY-MM-DD)')
    parser.add_argument('--debug', action='store_true', help='Enable debug output')
    args = parser.parse_args()

    result = scrape_exphuay(target_date=args.date, debug=args.debug)
    print(json.dumps(result, ensure_ascii=False))
