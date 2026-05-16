#!/usr/bin/env python3
"""
ExpHuay.com Lottery Scraper — ดึงผลหวยทุกประเภทจาก exphuay.com
ใช้ HTTP request ธรรมดา — ไม่ต้องเปิด browser (ประหยัด RAM, เร็วมาก)

ดึงจากหน้า /result สำหรับ "วันนี้" และใช้ backward สำหรับ "ย้อนหลัง"

Usage: python scripts/scrape_exphuay.py [--date=2026-03-21] [--debug]
Output: JSON to stdout
"""

import sys
import json
import re
import argparse
from datetime import datetime, date, timedelta
try:
    from zoneinfo import ZoneInfo
except ImportError:
    ZoneInfo = None
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
if hasattr(sys.stderr, 'reconfigure'):
    sys.stderr.reconfigure(encoding='utf-8')

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
    # 'laopattanamidday' = ลาวพัฒนาเที่ยง 12:30 (ไม่ใช่ลาวพัฒนาเย็น 20:15)

    # === ดาวโจนส์ ===
    'dowjonestar':      'dowjones-star',
}

# ============================================
# Fallback: all123th.com (ponhuay24) API slug → Key DB slug mapping
# Maps the 'results' field from LottoDetails API to our DB slug
# ============================================
ALL123TH_FALLBACK = {
    'dowjonespowerball': 'dowjones-vip',
    'dowjonestar':       'dowjones-star',
    'dowjonse-digital':  'dowjones',       # ดาวโจนส์ ดิจิตอล = ดาวโจนส์ปกติ (ออกผลเวลาเดียวกัน)
}

ALL123TH_API_URL = 'https://api.all123th.com/LottoDetails'

BACKWARD_BASE = 'https://exphuay.com/backward'
RESULT_URL = 'https://exphuay.com/result'
DETAIL_URL_TEMPLATE = 'https://exphuay.com/result/{slug}'
MINHNGOC_MB_RESULT_TEMPLATE = 'https://minhngoc.net.vn/ket-qua-xo-so/mien-bac/{date}.html'

OFFICIAL_FULL_RESULT_APIS = {
    'xsthm': 'https://app.all123th.com/get-awards/xsthm',
    'xosoredcross': 'https://app.all123th.com/get-awards/xosoredcross',
    'hanoiasean': 'https://app.all123th.com/get-awards/hanoiasean',
    'xosohd': 'https://app.all123th.com/get-awards/xosohd',
    'minhngoctv': 'https://app.all123th.com/get-awards/minhngoctv',
    'minhngocstar': 'https://app.all123th.com/get-awards/minhngocstar',
    'xosounion': 'https://app.all123th.com/get-awards/xosounion',
    'xosodevelop': 'https://app.all123th.com/get-awards/xosodevelop',
    'xosoextra': 'https://app.all123th.com/get-awards/xosoextra',
    'laoredcross': 'https://api.lao-redcross.com/result',
    'laoextra': 'https://api.laoextra.com/result',
    'laotv': 'https://api.lao-tv.com/result',
    'laoshd': 'https://api.laoshd.com/api/result',
    'laostars': 'https://api.laostars.com/result',
    'laostarsvip': 'https://api.laostars-vip.com/result',
    'laosasean': 'https://api.lotterylaosasean.com/api/result',
    'laounion': 'https://public-api.laounion.com/result',
    'laounionvip': 'https://api.laounionvip.com/result',
    'laosantipap': 'https://api.laosantipap.com/result',
    'laocitizen': 'https://api.laocitizen.com/result',
    'laopatuxay': 'https://api.laopatuxay.com/result',
    'laosvip': 'https://www.laosviplot.com/result',
}

HEADERS = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Language': 'th,en;q=0.5',
}


def current_timestamp():
    return datetime.now().strftime('%Y-%m-%d %H:%M:%S')


def now_bangkok():
    if ZoneInfo is not None:
        try:
            return datetime.now(ZoneInfo('Asia/Bangkok')).replace(tzinfo=None)
        except Exception:
            pass
    return datetime.utcnow() + timedelta(hours=7)


def iter_nested_values(node, keys):
    if isinstance(node, dict):
        for key, value in node.items():
            if str(key).lower() in keys:
                yield value
            yield from iter_nested_values(value, keys)
    elif isinstance(node, list):
        for item in node:
            yield from iter_nested_values(item, keys)


def parse_result_datetime(value):
    raw = str(value or '').strip()
    if not raw or raw in ('0000-00-00 00:00:00', '-'):
        return None
    raw = raw.replace('T', ' ').replace('Z', '').strip()
    raw = re.sub(r'\.\d+', '', raw)
    formats = (
        '%Y-%m-%d %H:%M:%S',
        '%Y-%m-%d %H:%M',
        '%d/%m/%Y %H:%M:%S',
        '%d/%m/%Y %H:%M',
    )
    for fmt in formats:
        try:
            return datetime.strptime(raw[:len(datetime.now().strftime(fmt))], fmt)
        except ValueError:
            continue
    try:
        parsed = datetime.fromisoformat(raw)
        return parsed.replace(tzinfo=None)
    except ValueError:
        return None


def is_payload_result_visible(payload, debug=False, label=''):
    show_keys = {'show_result', 'showresult', 'show_at', 'showat', 'result_time', 'resulttime'}
    for value in iter_nested_values(payload, show_keys):
        show_at = parse_result_datetime(value)
        if show_at is None:
            continue
        if now_bangkok() < show_at:
            if debug:
                print(f'[ExpHuay]   ⏳ {label}: show_result={show_at:%Y-%m-%d %H:%M:%S} not reached', file=sys.stderr)
            return False
    return True


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


def fetch_json(url, debug=False):
    """Fetch JSON from an official result API."""
    try:
        req = Request(url, headers={**HEADERS, 'Accept': '*/*'})
        with urlopen(req, timeout=15) as response:
            payload = json.loads(response.read().decode('utf-8'))
            if debug:
                print(f'[ExpHuay]   JSON OK {url}', file=sys.stderr)
            return payload
    except (URLError, HTTPError, json.JSONDecodeError, TimeoutError, OSError) as e:
        if debug:
            print(f'[ExpHuay]   JSON Error {url}: {e}', file=sys.stderr)
        return None


def build_official_result(key_slug, draw_date, first_prize, source, three_top='', two_top='', two_bottom=''):
    """Normalize official/full result data for Key DB, using fallback values when some digits are absent."""
    first_prize = re.sub(r'\D+', '', str(first_prize or ''))
    three_top = re.sub(r'\D+', '', str(three_top or ''))
    two_top = re.sub(r'\D+', '', str(two_top or ''))
    two_bottom = re.sub(r'\D+', '', str(two_bottom or ''))

    if len(first_prize) < 4 and not three_top and not two_bottom:
        return None

    if not three_top and len(first_prize) >= 3:
        three_top = first_prize[-3:]
    if not two_top and len(three_top) >= 2:
        two_top = three_top[-2:]

    result = {
        'slug': key_slug,
        'first_prize': first_prize,
        'four_top': first_prize[-4:] if len(first_prize) >= 4 else '',
        'draw_date': draw_date,
        'source': source,
        'detected_4top_at': current_timestamp() if len(first_prize) >= 4 else '',
        'detected_4top_source': source if len(first_prize) >= 4 else '',
    }

    if three_top:
        result['three_top'] = three_top
    if two_top:
        result['two_top'] = two_top
    if two_bottom:
        result['two_bottom'] = two_bottom
    if three_top and two_bottom:
        result['detected_32_at'] = current_timestamp()
        result['detected_32_source'] = source

    return result


def scrape_official_full_api(exphuay_slug, key_slug, target_date, debug=False):
    """Try official APIs that expose the 5-digit prize."""
    api_url = OFFICIAL_FULL_RESULT_APIS.get(exphuay_slug)
    if not api_url:
        return None

    payload = fetch_json(api_url, debug)
    if not isinstance(payload, dict):
        return None
    if not is_payload_result_visible(payload, debug, exphuay_slug):
        return None

    if exphuay_slug == 'laosvip':
        draw_date = target_date
        raw_date = str(payload.get('date') or '')
        if re.match(r'^\d{2}/\d{2}/\d{4}$', raw_date):
            day, month, year = raw_date.split('/')
            draw_date = f'{year}-{month}-{day}'
        first_prize = ''.join(str(payload.get(f'lotto_{index}', '')) for index in range(5))
        result = build_official_result(key_slug, draw_date, first_prize, api_url)
        if result and debug:
            print(f'[ExpHuay]   official API {exphuay_slug} -> {result["four_top"]}', file=sys.stderr)
        return result

    if payload.get('status') == 'success':
        data = payload.get('data') or {}
        results = data.get('results') or {}
        draw_date = str(data.get('lotto_date') or target_date)
        first_prize = results.get('digit5') or ''
        three_top = results.get('digit3') or results.get('digit3_top') or ''
        two_top = results.get('digit2_top') or ''
        two_bottom = results.get('digit2_bottom') or ''
    else:
        draw_date = str(payload.get('lotto_date') or payload.get('date') or target_date)
        first_prize = payload.get('digit5') or ''
        three_top = payload.get('digit3') or payload.get('digit3_top') or ''
        two_top = payload.get('digit2_top') or ''
        two_bottom = payload.get('digit2_bottom') or ''

    result = build_official_result(key_slug, draw_date, first_prize, api_url, three_top, two_top, two_bottom)
    if result and debug:
        summary_bits = []
        if result.get('three_top'):
            summary_bits.append(str(result.get('three_top')))
        if result.get('two_bottom'):
            summary_bits.append(str(result.get('two_bottom')))
        if result.get('four_top'):
            summary_bits.append(f'4={result.get("four_top")}')
        print(f'[ExpHuay]   official API {exphuay_slug} -> {" / ".join(summary_bits)}', file=sys.stderr)
    return result


def scrape_detail_page_full_result(exphuay_slug, key_slug, target_date, debug=False):
    """Fallback to the per-lottery detail page, which contains the 5-digit top result."""
    html = fetch_page(DETAIL_URL_TEMPLATE.format(slug=exphuay_slug), debug)
    if not html:
        return None

    first_match = re.search(r'ผลรางวัล.*?(\d{5})', html, re.S)
    if not first_match:
        return None

    first_prize = first_match.group(1)
    result = build_official_result(key_slug, target_date, first_prize, f'exphuay.com/result/{exphuay_slug}')
    if result and debug:
        print(f'[ExpHuay]   detail page {exphuay_slug} -> {result["four_top"]}', file=sys.stderr)
    return result


def scrape_detail_page_result_bundle(exphuay_slug, key_slug, target_date, debug=False):
    """Parse first prize, 3-top and 2-bottom from the detail page when available."""
    html = fetch_page(DETAIL_URL_TEMPLATE.format(slug=exphuay_slug), debug)
    if not html:
        return None

    detail_match = re.search(
        r'<!--\[-->(\d{5})<!--\]-->.*?<ul class="grid grid-cols-2">.*?<!--\[-->(\d{3})<!--\]-->.*?<!--\[-->(\d{2})<!--\]-->',
        html,
        re.S,
    )
    if not detail_match:
        return None

    result = build_official_result(
        key_slug,
        target_date,
        detail_match.group(1),
        f'exphuay.com/result/{exphuay_slug}',
        detail_match.group(2),
        '',
        detail_match.group(3),
    )
    if result and debug:
        summary_bits = []
        if result.get('three_top'):
            summary_bits.append(str(result.get('three_top')))
        if result.get('two_bottom'):
            summary_bits.append(str(result.get('two_bottom')))
        if result.get('four_top'):
            summary_bits.append(f'4={result.get("four_top")}')
        print(f'[ExpHuay]   detail bundle {exphuay_slug} -> {" / ".join(summary_bits)}', file=sys.stderr)
    return result


def scrape_minhngoc_hanoi_full_result(key_slug, target_date, debug=False):
    """Fetch Hanoi normal 5-digit result from Minh Ngoc's Mien Bac result page."""
    dd_mm_yyyy = datetime.strptime(target_date, '%Y-%m-%d').strftime('%d-%m-%Y')
    url = MINHNGOC_MB_RESULT_TEMPLATE.format(date=dd_mm_yyyy)
    html = fetch_page(url, debug)
    if not html:
        return None

    table_match = re.search(
        r'<table[^>]+class="bkqtinhmienbac"[^>]*>.*?<td class="giaidb">\s*<div>(\d{5})</div>',
        html,
        re.S | re.I,
    )
    if not table_match:
        return None

    result = build_official_result(key_slug, target_date, table_match.group(1), url)
    if result and debug:
        print(f'[ExpHuay]   minhngoc page -> {result["four_top"]}', file=sys.stderr)
    return result


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


def extract_number(cell_html, digits):
    """Extract a 2/3-digit value from a result cell only."""
    if not cell_html or 'loading.svg' in cell_html or 'Loading...' in cell_html:
        return None

    match = re.search(rf'(?<!\d)(\d{{{digits}}})(?!\d)', cell_html)
    return match.group(1) if match else None


def scrape_result_page(target_date, debug=False):
    """Scrape today's live result page row-by-row."""
    html = fetch_page(RESULT_URL, debug)
    if not html:
        return []

    results = []
    seen = set()
    row_pattern = re.compile(
        r'<li class="grid grid-cols-4.*?</li>',
        re.DOTALL,
    )
    result_cell_pattern = re.compile(
        r'<span class="text-center text-lg font-bold">(.*?)</span>\s*'
        r'<span class="text-center text-lg font-bold">(.*?)</span>',
        re.DOTALL,
    )

    for row_match in row_pattern.finditer(html):
        row_html = row_match.group(0)
        href_match = re.search(r'href="/result/([^"?#]+)', row_html)
        if not href_match:
            continue

        exphuay_slug = href_match.group(1)
        key_slug = EXPHUAY_LOTTERIES.get(exphuay_slug)
        if not key_slug or key_slug in seen:
            continue

        result_match = result_cell_pattern.search(row_html)
        if not result_match:
            if debug:
                print(f'[ExpHuay]   ⚠️ {exphuay_slug}: row found but result cells missing', file=sys.stderr)
            continue

        three_top = extract_number(result_match.group(1), 3)
        two_bottom = extract_number(result_match.group(2), 2)
        if not three_top or not two_bottom:
            if debug:
                print(f'[ExpHuay]   ⏭️ {exphuay_slug}: no published result yet', file=sys.stderr)
            continue

        seen.add(key_slug)
        results.append({
            'slug': key_slug,
            'three_top': three_top,
            'two_top': three_top[-2:],
            'two_bottom': two_bottom,
            'draw_date': target_date,
            'source': 'exphuay.com',
            'detected_32_at': current_timestamp(),
            'detected_32_source': 'exphuay.com',
        })

        if debug:
            print(f'[ExpHuay]   ✅ {exphuay_slug} → {three_top}/{two_bottom}', file=sys.stderr)

    if debug:
        print(f'[ExpHuay] 📊 /result page: {len(results)} results found', file=sys.stderr)

    return results


def enrich_today_results_with_full_sources(summary_results, target_date, debug=False):
    """Enrich Lao/Hanoi lotteries with official sources, but keep Exphuay as authority for 3/2 when it exists."""
    results_by_slug = {row['slug']: dict(row) for row in summary_results}

    for exphuay_slug, key_slug in EXPHUAY_LOTTERIES.items():
        if not (key_slug.startswith('lao-') or key_slug == 'hanoi' or key_slug.startswith('hanoi-')):
            continue

        full_result = None
        if exphuay_slug == 'minhngoc':
            full_result = scrape_minhngoc_hanoi_full_result(key_slug, target_date, debug)
        if full_result is None:
            full_result = scrape_official_full_api(exphuay_slug, key_slug, target_date, debug)
        if full_result is None:
            full_result = scrape_detail_page_full_result(exphuay_slug, key_slug, target_date, debug)

        if full_result is not None and (
            not full_result.get('three_top') or not full_result.get('two_bottom')
        ):
            detail_bundle = scrape_detail_page_result_bundle(exphuay_slug, key_slug, target_date, debug)
            if detail_bundle is not None:
                for field in (
                    'first_prize',
                    'four_top',
                    'three_top',
                    'two_top',
                    'two_bottom',
                    'source',
                    'detected_32_at',
                    'detected_32_source',
                    'detected_4top_at',
                    'detected_4top_source',
                ):
                    value = detail_bundle.get(field)
                    if value not in (None, '') and not full_result.get(field):
                        full_result[field] = value

        existing_summary = results_by_slug.get(key_slug)
        if existing_summary is not None and full_result is not None:
            existing_three = str(existing_summary.get('three_top') or '')
            existing_two_bottom = str(existing_summary.get('two_bottom') or '')
            official_three = str(full_result.get('three_top') or '')
            official_two_bottom = str(full_result.get('two_bottom') or '')

            mismatch_bits = []
            if existing_three and official_three and existing_three != official_three:
                mismatch_bits.append(f'3บน exphuay={existing_three} official={official_three}')
            if existing_two_bottom and official_two_bottom and existing_two_bottom != official_two_bottom:
                mismatch_bits.append(f'2ล่าง exphuay={existing_two_bottom} official={official_two_bottom}')

            if mismatch_bits:
                full_result['three_top'] = existing_three or official_three
                full_result['two_top'] = (existing_summary.get('two_top') or (existing_three[-2:] if existing_three else full_result.get('two_top', '')))
                full_result['two_bottom'] = existing_two_bottom or official_two_bottom
                full_result['detected_32_at'] = existing_summary.get('detected_32_at', full_result.get('detected_32_at', ''))
                full_result['detected_32_source'] = existing_summary.get('detected_32_source', 'exphuay.com')
                full_result['compare_32_status'] = 'mismatch_override_exphuay'
                full_result['compare_32_message'] = '; '.join(mismatch_bits)
                if debug:
                    print(f'[ExpHuay]   override {exphuay_slug} 3/2 by exphuay -> {"; ".join(mismatch_bits)}', file=sys.stderr)

        if full_result is None:
            if debug:
                print(f'[ExpHuay]   no four-top enrichment for {exphuay_slug}', file=sys.stderr)
            continue

        existing = results_by_slug.get(key_slug, {})
        merged = dict(existing)
        merged['slug'] = key_slug
        merged['draw_date'] = existing.get('draw_date') or target_date
        for field in (
            'first_prize',
            'four_top',
            'three_top',
            'two_top',
            'two_bottom',
            'source',
        ):
            value = full_result.get(field)
            if value not in (None, ''):
                merged[field] = value
        for field in (
            'detected_32_at',
            'detected_32_source',
            'detected_4top_at',
            'detected_4top_source',
        ):
            value = full_result.get(field)
            if value not in (None, ''):
                merged[field] = value
        results_by_slug[key_slug] = merged

    return list(results_by_slug.values())

def fetch_all123th_dowjones_results(target_date, debug=False):
    """Fetch all123th Dow Jones rows as enrichment candidates only."""
    fallback_by_slug = {}
    try:
        req = Request(ALL123TH_API_URL, headers={
            **HEADERS,
            'Accept': 'application/json, text/plain, */*',
            'Origin': 'https://ponhuay24.com',
            'Referer': 'https://ponhuay24.com/',
        })
        with urlopen(req, timeout=15) as response:
            data = json.loads(response.read().decode('utf-8'))
    except (URLError, HTTPError, json.JSONDecodeError, TimeoutError, OSError) as e:
        if debug:
            print(f'[Fallback] ❌ API error: {e}', file=sys.stderr)
        return fallback_by_slug

    items = data if isinstance(data, list) else []

    for item in items:
        api_slug = str(item.get('results', '') or '')
        key_slug = ALL123TH_FALLBACK.get(api_slug)
        if not key_slug:
            continue

        awards = item.get('awards')
        if not isinstance(awards, dict):
            continue

        awards_all = str(awards.get('all', '') or '').strip()
        awards_date = str(awards.get('date', '') or '').strip()
        awards_bottom = str(awards.get('bottom', '') or '').strip()  # 3 ตัวบน (last 3 digits)
        awards_top = str(awards.get('top', '') or '').strip()       # 2 ตัวล่าง (first 2 digits)

        if not awards_bottom or not awards_top or len(awards_bottom) != 3 or len(awards_top) != 2:
            if debug:
                print(f'[Fallback] ⏭️ {api_slug}: incomplete data (bottom={awards_bottom}, top={awards_top})', file=sys.stderr)
            continue

        if not re.match(r'^\d{3}$', awards_bottom) or not re.match(r'^\d{2}$', awards_top):
            if debug:
                print(f'[Fallback] ⏭️ {api_slug}: non-numeric data', file=sys.stderr)
            continue

        # Use awards_date if available, otherwise use target_date
        draw_date = awards_date if re.match(r'^\d{4}-\d{2}-\d{2}$', awards_date) else target_date

        result = {
            'slug': key_slug,
            'three_top': awards_bottom,
            'two_top': awards_bottom[-2:],
            'two_bottom': awards_top,
            'draw_date': draw_date,
            'source': 'all123th.com (fallback)',
            'detected_32_at': current_timestamp(),
            'detected_32_source': 'all123th.com',
        }

        if len(awards_all) >= 4:
            result['four_top'] = awards_all[-4:]
            result['first_prize'] = awards_all
            result['detected_4top_at'] = current_timestamp()
            result['detected_4top_source'] = 'all123th.com'

        fallback_by_slug[key_slug] = result

        if debug:
            print(f'[Fallback] ✅ {api_slug} → {key_slug}: {awards_bottom}/{awards_top} (date={draw_date})', file=sys.stderr)

    if debug:
        print(f'[Fallback] 📊 {len(fallback_by_slug)} enrichment candidates', file=sys.stderr)

    return fallback_by_slug


def enrich_dowjones_four_top_with_all123th(results, target_date, debug=False):
    """Use all123th only to fill missing 4-top after ExpHuay already has matching 3/2."""
    fallback_by_slug = fetch_all123th_dowjones_results(target_date, debug)
    if not fallback_by_slug:
        return results

    enriched = []
    for result in results:
        key_slug = result.get('slug')
        fallback = fallback_by_slug.get(key_slug)
        if not fallback:
            enriched.append(result)
            continue

        existing_three = str(result.get('three_top') or '')
        existing_two_bottom = str(result.get('two_bottom') or '')
        fallback_three = str(fallback.get('three_top') or '')
        fallback_two_bottom = str(fallback.get('two_bottom') or '')

        # Never let fallback become the authority for 3/2. It can only enrich a confirmed ExpHuay result.
        if not existing_three or not existing_two_bottom:
            if debug:
                print(f'[Fallback] ⏭️ {key_slug}: ExpHuay 3/2 missing, skip fallback authority', file=sys.stderr)
            enriched.append(result)
            continue

        mismatch_bits = []
        if fallback_three and existing_three != fallback_three:
            mismatch_bits.append(f'3บน exphuay={existing_three} all123th={fallback_three}')
        if fallback_two_bottom and existing_two_bottom != fallback_two_bottom:
            mismatch_bits.append(f'2ล่าง exphuay={existing_two_bottom} all123th={fallback_two_bottom}')

        merged = dict(result)
        if mismatch_bits:
            merged['compare_32_status'] = 'conflict_all123th'
            merged['compare_32_message'] = '; '.join(mismatch_bits)
            if debug:
                print(f'[Fallback] ⚠️ {key_slug}: {"; ".join(mismatch_bits)}', file=sys.stderr)
            enriched.append(merged)
            continue

        if not merged.get('four_top') and fallback.get('four_top'):
            merged['four_top'] = fallback.get('four_top')
            merged['first_prize'] = fallback.get('first_prize', '')
            merged['detected_4top_at'] = fallback.get('detected_4top_at', current_timestamp())
            merged['detected_4top_source'] = fallback.get('detected_4top_source', 'all123th.com')
            if debug:
                print(f'[Fallback] ✅ {key_slug}: enriched 4top={merged["four_top"]}', file=sys.stderr)

        enriched.append(merged)

    return enriched


def scrape_exphuay(target_date=None, debug=False):
    """Main scraper — fetch all lotteries from ExpHuay, with all123th.com fallback"""
    if not target_date:
        target_date = date.today().strftime('%Y-%m-%d')

    results = []
    today = date.today().strftime('%Y-%m-%d')

    # วันนี้ใช้หน้า /result เท่านั้น เพราะเป็น live board ของวันนั้น
    if target_date == today:
        results = scrape_result_page(target_date, debug)
        results = enrich_today_results_with_full_sources(results, target_date, debug)

        # Fallback: ใช้ all123th เสริมเฉพาะ 4 ตัวบนของดาวโจนส์ เมื่อ ExpHuay มี 3/2 ตรงกันแล้วเท่านั้น
        results = enrich_dowjones_four_top_with_all123th(results, target_date, debug)

        return {
            'success': len(results) > 0,
            'results': results,
            'total': len(results),
            'scraped_at': datetime.now().isoformat(),
            'source': 'exphuay.com/result + all123th.com 4top enrichment',
        }

    lookup_date = target_date
    skipped = 0
    failed = 0

    for exphuay_slug, key_slug in EXPHUAY_LOTTERIES.items():
        url = f'{BACKWARD_BASE}/{exphuay_slug}'
        if debug:
            print(f'[ExpHuay] 🌐 {exphuay_slug} → {key_slug} (lookup={lookup_date}, draw={target_date})...', file=sys.stderr)

        html = fetch_page(url, debug)
        result = parse_backward_page(html, lookup_date, exphuay_slug, key_slug, debug)

        if result and result != 'NO_DRAW':
            result['draw_date'] = target_date
            if debug:
                print(f'[ExpHuay]   ✅ {result["three_top"]} / {result["two_top"]} / {result["two_bottom"]}', file=sys.stderr)
            results.append(result)
        elif result == 'NO_DRAW':
            skipped += 1
        else:
            if html and f'date={lookup_date}' not in html:
                skipped += 1
                if debug:
                    print(f'[ExpHuay]   ⏭️ ไม่มีผลวันที่ lookup {lookup_date}', file=sys.stderr)
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
