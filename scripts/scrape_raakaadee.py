#!/usr/bin/env python3
"""
Raakaadee.com Lottery Scraper v3
ดึงผลหวยจาก raakaadee.com ด้วย Camoufox (bypass Cloudflare)
Scrape แยกหน้าตามหมวดหวย เพื่อความแม่นยำ

Usage: .venv/bin/python scripts/scrape_raakaadee.py [--debug] [--slug=china-morning-vip] [--url=...]
Output: JSON to stdout
"""

import sys
import json
import re
import argparse
from datetime import datetime, timedelta

# ============ CATEGORY PAGES TO SCRAPE ============
# แต่ละหน้ามีผลหวยเฉพาะหมวดนั้น ดึงแม่นกว่าหน้าหลัก
CATEGORY_URLS = [
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยพิเศษ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย-VIP/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวพัฒนา/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสตาร์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวประตูชัย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นจีน/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นนิเคอิ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นฮั่งเส็ง/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นไต้หวัน/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นเกาหลี/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นสิงคโปร์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอินเดีย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอียิปต์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นอังกฤษ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นเยอรมัน/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นรัสเซีย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นดาวโจนส์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้นดาวโจนส์สตาร์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสามัคคี/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-VIP/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวกาชาด/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสันติภาพ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวใต้/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยประชาชนลาว/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-Extra/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวทีวี/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยตรุษจีน/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยมาเลย์/',
    'https://www.raakaadee.com/',  # หน้าหลัก — fallback
]

# ============ LOTTERY NAME → SLUG MAPPING ============
# เฉพาะหวยที่มีอยู่ในระบบ asawin89.com เท่านั้น
LOTTERY_MAPPINGS = {
    # === หุ้น VIP ===
    'นิเคอิเช้า VIP': 'nikkei-morning-vip',
    'นิเคอิเช้าVIP': 'nikkei-morning-vip',
    'นิเคอิบ่าย VIP': 'nikkei-afternoon-vip',
    'นิเคอิบ่ายVIP': 'nikkei-afternoon-vip',
    'จีนเช้า VIP': 'china-morning-vip',
    'จีนเช้าVIP': 'china-morning-vip',
    'จีนบ่าย VIP': 'china-afternoon-vip',
    'จีนบ่ายVIP': 'china-afternoon-vip',
    'ฮั่งเส็งเช้า VIP': 'hangseng-morning-vip',
    'ฮั่งเส็งเช้าVIP': 'hangseng-morning-vip',
    'ฮั่งเส็งบ่าย VIP': 'hangseng-afternoon-vip',
    'ฮั่งเส็งบ่ายVIP': 'hangseng-afternoon-vip',
    'ไต้หวัน VIP': 'taiwan-vip',
    'ไต้หวันVIP': 'taiwan-vip',
    'สิงคโปร์ VIP': 'singapore-vip',
    # egypt-vip, india-vip, russia-vip → ใช้ scraper เดิม
    'อังกฤษ VIP': 'uk-vip',
    'เยอรมัน VIP': 'germany-vip',
    'ดาวโจนส์ VIP': 'dowjones-vip',
    'ดาวโจนส์ STAR': 'dowjones-star',
    'เกาหลี VIP': 'korea-vip',
    'เกาหลีVIP': 'korea-vip',

    # === หุ้นปกติ ===
    'หุ้นนิเคอิเช้า': 'nikkei-morning',
    'หุ้นนิเคอิบ่าย': 'nikkei-afternoon',
    'หุ้นจีนเช้า': 'china-morning',
    'หุ้นจีนบ่าย': 'china-afternoon',
    'หุ้นฮั่งเส็งเช้า': 'hangseng-morning',
    'หุ้นฮั่งเส็งบ่าย': 'hangseng-afternoon',
    'หุ้นไต้หวัน': 'taiwan',
    'ไต้หวันพิเศษ': 'taiwan',
    'หุ้นเกาหลี': 'korea',
    'เกาหลีพิเศษ': 'korea',
    'หุ้นสิงคโปร์': 'singapore',
    'หุ้นอินเดีย': 'india',
    'หุ้นอียิปต์': 'egypt',
    'หุ้นอังกฤษ': 'uk',
    'หุ้นเยอรมัน': 'germany',
    'หุ้นรัสเซีย': 'russia',
    'หุ้นดาวโจนส์': 'dowjones',
    'ดาวโจนส์สตาร์': 'dowjones-star',
    'ดาวโจนส์ STAR': 'dowjones-star',
    'หุ้นดาวโจนส์สตาร์': 'dowjones-star',
    'หุ้นดาวโจนส์ STAR': 'dowjones-star',
    # หุ้นไทยเช้า/เย็น → ใช้ scraper เดิม (ไม่ดึงจาก raakaadee)

    # === หวยฮานอย ===
    'ฮานอยปกติ': 'hanoi',
    'ฮานอย ออก': 'hanoi',
    'ฮานอย VIP': 'hanoi-vip',
    'ฮานอยVIP': 'hanoi-vip',
    'ฮานอยพิเศษ': 'hanoi-special',
    'ฮานอยเฉพาะกิจ': 'hanoi-adhoc',
    'ฮานอยกาชาด': 'hanoi-redcross',
    'ฮานอยอาเซียน': 'hanoi-asean',
    'ฮานอย HD': 'hanoi-hd',
    'ฮานอยHD': 'hanoi-hd',
    'ฮานอย TV': 'hanoi-tv',
    'ฮานอยTV': 'hanoi-tv',
    'ฮานอยทีวี': 'hanoi-tv',
    'ฮานอยสตาร์': 'hanoi-star',
    'ฮานอยสามัคคี': 'hanoi-samakki',
    'ฮานอยตรุษจีน': 'hanoi-chinese-ny',
    'ฮานอยพัฒนา': 'hanoi-pattana',
    'ฮานอย EXTRA': 'hanoi-extra',
    'ฮานอยEXTRA': 'hanoi-extra',
    'ฮานอย Extra': 'hanoi-extra',

    # === หวยลาว ===
    'ลาวพัฒนา': 'lao-pattana',
    'ลาว VIP': 'lao-vip',
    'ลาวVIP': 'lao-vip',
    'ลาวสตาร์': 'lao-star',
    'ลาวสตาร์ VIP': 'lao-star-vip',
    'ลาวสตาร์VIP': 'lao-star-vip',
    'ลาวสามัคคี': 'lao-samakki',
    'ลาวสามัคคี VIP': 'lao-samakki-vip',
    'ลาวสามัคคีVIP': 'lao-samakki-vip',
    'ลาวประตูชัย': 'lao-pratuchai',
    'ลาวสันติภาพ': 'lao-santiphap',
    'ประชาชนลาว': 'lao-prachachon',
    'ลาว EXTRA': 'lao-extra',
    'ลาวEXTRA': 'lao-extra',
    'ลาว Extra': 'lao-extra',
    'ลาวเอ็กซ์ตร้า': 'lao-extra',
    'ลาว TV': 'lao-tv',
    'ลาวTV': 'lao-tv',
    'ลาวทีวี': 'lao-tv',
    'ลาว HD': 'lao-hd',
    'ลาวHD': 'lao-hd',
    'ลาวใต้': 'lao-tai',
    'ลาวอาเซียน': 'lao-asean',
    'ลาวกาชาด': 'lao-redcross',

    # === อื่นๆ ===
    'สลากกินแบ่งรัฐบาล': 'thai',
    'สลากออมสิน': 'gsb',
    'ออมสิน': 'gsb',
    'หวยมาเลย์': 'malay',
    'ธกส': 'baac',
    'หวย 12 ราศี': 'rasi-12',
}

# Thai month abbreviations/full → month number
THAI_MONTHS = {
    'ม.ค.': 1, 'มกราคม': 1,
    'ก.พ.': 2, 'กุมภาพันธ์': 2,
    'มี.ค.': 3, 'มีนาคม': 3,
    'เม.ย.': 4, 'เมษายน': 4,
    'พ.ค.': 5, 'พฤษภาคม': 5,
    'มิ.ย.': 6, 'มิถุนายน': 6,
    'ก.ค.': 7, 'กรกฎาคม': 7,
    'ส.ค.': 8, 'สิงหาคม': 8,
    'ก.ย.': 9, 'กันยายน': 9,
    'ต.ค.': 10, 'ตุลาคม': 10,
    'พ.ย.': 11, 'พฤศจิกายน': 11,
    'ธ.ค.': 12, 'ธันวาคม': 12,
}



def match_slug(text):
    """Match text against known lottery names — longest match first, prioritizing VIP"""
    text_clean = re.sub(r'\s+', ' ', text.strip())
    text_lower = text_clean.lower()
    
    # If text contains VIP, try to match VIP keys first
    if 'vip' in text_lower:
        for key in sorted([k for k in LOTTERY_MAPPINGS.keys() if 'vip' in k.lower()], key=len, reverse=True):
            if key in text_clean:
                return LOTTERY_MAPPINGS[key], key
                
    # Then normal keys
    for key in sorted(LOTTERY_MAPPINGS.keys(), key=len, reverse=True):
        if key in text_clean:
            # Prevent VIP text from matching normal keys if somehow missed
            if 'vip' in text_lower and 'vip' not in key.lower() and key in text_clean:
                continue
            return LOTTERY_MAPPINGS[key], key
            
    return None, None


def parse_thai_date(text):
    """
    Parse Thai date from ตรวจผล line, e.g.:
      "ตรวจผล หวยฮานอยปกติ ออก จ. 10 มี.ค. 69 เวลา 18:30 น."
      "ตรวจผล หวยฮานอยปกติ ออก อ. 10 มีนาคม 2569 เวลา 18:30 น."
    Returns 'YYYY-MM-DD' string or None
    """
    # Match: day (1-2 digits) + Thai month + year (2 or 4 digits)
    for month_key, month_num in sorted(THAI_MONTHS.items(), key=lambda x: len(x[0]), reverse=True):
        pattern = rf'(\d{{1,2}})\s+{re.escape(month_key)}\s+(\d{{2,4}})'
        m = re.search(pattern, text)
        if m:
            day = int(m.group(1))
            year_be = int(m.group(2))
            # Convert Buddhist Era to AD
            if year_be < 100:
                year_ad = year_be + 2500 - 543  # 69 → 2026
            elif year_be > 2500:
                year_ad = year_be - 543          # 2569 → 2026
            else:
                year_ad = year_be
            try:
                return f'{year_ad:04d}-{month_num:02d}-{day:02d}'
            except:
                return None
    return None


def parse_page_results(lines, found_slugs, today, yesterday, debug=False):
    """Parse results from a single page's lines"""
    results = []
    # คำนวณวันที่ย้อนหลัง 3 วัน (สำหรับวันหยุดยาว/สุดสัปดาห์)
    day2 = (datetime.now() - timedelta(days=2)).strftime('%Y-%m-%d')
    day3 = (datetime.now() - timedelta(days=3)).strftime('%Y-%m-%d')

    # หุ้นทั้งหมด — ยอมรับผลย้อนหลัง 3 วัน (วันหยุด/สุดสัปดาห์)
    all_stock_slugs = {
        # หุ้นต่างประเทศ (ปิดหลังเที่ยงคืนไทย)
        'dowjones', 'dowjones-vip', 'dowjones-star',
        'uk', 'uk-vip',
        'germany', 'germany-vip',
        'russia', 'russia-vip',
        # หุ้นเอเชีย
        'nikkei-morning', 'nikkei-afternoon',
        'nikkei-morning-vip', 'nikkei-afternoon-vip',
        'china-morning', 'china-afternoon',
        'china-morning-vip', 'china-afternoon-vip',
        'hangseng-morning', 'hangseng-afternoon',
        'hangseng-morning-vip', 'hangseng-afternoon-vip',
        'taiwan', 'taiwan-vip',
        'korea', 'korea-vip',
        'singapore', 'singapore-vip',
        'india', 'egypt',
    }

    for i, line in enumerate(lines):
        line_stripped = line.strip()

        # Only process lines containing "ตรวจผล" (actual results)
        if 'ตรวจผล' not in line_stripped:
            continue

        slug, matched_name = match_slug(line_stripped)
        if not slug or slug in found_slugs:
            if debug and not slug:
                short = line_stripped[:60]
                print(f'[Raakaadee]   ⚠️ No match: "{short}"', file=sys.stderr)
            continue

        # === Skip lotteries handled by ExpHuay (more accurate source) ===
        EXPHUAY_ONLY = {'germany'}  # หุ้นเยอรมัน — Raakaadee ดึงผลผิดวัน
        if slug in EXPHUAY_ONLY:
            if debug:
                print(f'[Raakaadee]   ⏭️ {matched_name}: ข้ามไป ExpHuay', file=sys.stderr)
            continue

        # === Date validation: skip stale results ===
        draw_date = parse_thai_date(line_stripped)
        if draw_date:
            # หุ้นทุกตัว: ยอมรับ 3 วันย้อนหลัง (สุดสัปดาห์/วันหยุดยาว)
            if slug in all_stock_slugs:
                valid_dates = [today, yesterday, day2, day3]
            else:
                valid_dates = [today, yesterday]
            if draw_date not in valid_dates:
                if debug:
                    print(f'[Raakaadee]   ⏭️ {matched_name}: ผลเก่า ({draw_date} not in {valid_dates}) → ข้าม', file=sys.stderr)
                continue

        # === Time validation: skip wrong draw rounds ===
        # ลาวพัฒนามีรอบเที่ยง+ค่ำ → ต้องการแค่ 20:30
        REQUIRED_DRAW_TIMES = {
            'lao-pattana': 20,  # ลาวพัฒนา: เอาแค่รอบ 20:30 (ค่ำ) ไม่เอารอบเที่ยง 12:30
        }
        if slug in REQUIRED_DRAW_TIMES:
            time_m = re.search(r'เวลา\s*(\d{1,2})[:.](\d{2})', line_stripped)
            if time_m:
                draw_hour = int(time_m.group(1))
                required_hour = REQUIRED_DRAW_TIMES[slug]
                if draw_hour < required_hour:
                    if debug:
                        print(f'[Raakaadee]   ⏭️ {matched_name}: รอบ {draw_hour}:00 ≠ {required_hour}:00 (ต้องการรอบค่ำ) → ข้าม', file=sys.stderr)
                    continue

        # Look ahead for "3 ตัวบน" and "2 ตัวล่าง"
        three_top = None
        two_bottom = None

        for j in range(i+1, min(i+10, len(lines))):
            next_line = lines[j].strip()
            if 'ตรวจผล' in next_line:
                break  # Next lottery section
            if 'งดออกผล' in next_line:
                if debug:
                    print(f'[Raakaadee]   ⏭️ {matched_name}: งดออกผล → {slug}', file=sys.stderr)
                break  # No results today

            top_m = re.match(r'3\s*ตัวบน\s+(\d{3})', next_line)
            if top_m:
                three_top = top_m.group(1)

            bottom_m = re.match(r'2\s*ตัวล่าง\s*(\d{2})', next_line)
            if bottom_m:
                two_bottom = bottom_m.group(1)

        if three_top:
            results.append({
                'slug': slug,
                'lottery_name': matched_name,
                'first_prize': three_top,
                'three_top': three_top,
                'two_top': three_top[-2:],
                'two_bottom': two_bottom or '',
                'draw_date': draw_date or today,
                'source': 'raakaadee.com',
            })
            found_slugs.add(slug)
            print(f'[Raakaadee]   ✅ {matched_name}: {three_top} / {three_top[-2:]} / {two_bottom or "?"} → {slug} ({draw_date or today})', file=sys.stderr)
        elif debug:
            print(f'[Raakaadee]   ⚠️ {matched_name}: ไม่พบ 3 ตัวบน → {slug}', file=sys.stderr)

    return results


def safe_evaluate(page, expr, default=''):
    """Safely evaluate JS, return default if page not ready"""
    try:
        result = page.evaluate(expr)
        return result if result else default
    except Exception:
        return default


def load_page(page, url, debug=False, max_retries=3):
    """Load a page, handle Cloudflare challenge with retries, return text"""
    print(f'[Raakaadee] 🌐 Loading {url}...', file=sys.stderr)

    for attempt in range(max_retries):
        try:
            page.goto(url, timeout=60000, wait_until='domcontentloaded')
            try:
                page.wait_for_load_state('networkidle', timeout=15000)
            except Exception:
                pass  # networkidle can timeout, that's ok

            # Cloudflare wait
            max_wait = 30
            waited = 0
            while waited < max_wait:
                text = safe_evaluate(page, '() => document.body ? document.body.innerText : ""')
                if not text:
                    page.wait_for_timeout(2000)
                    waited += 2
                    continue
                if 'checking your browser' in text.lower() or 'please wait' in text.lower() or 'just a moment' in text.lower():
                    if waited == 0:
                        print(f'[Raakaadee] ⏳ Cloudflare challenge...', file=sys.stderr)
                    page.wait_for_timeout(3000)
                    waited += 3
                else:
                    if waited > 0:
                        print(f'[Raakaadee] ✅ Cloudflare passed! ({waited}s)', file=sys.stderr)
                    break
            else:
                print(f'[Raakaadee] ⚠️ Cloudflare timeout ({max_wait}s)', file=sys.stderr)
                if attempt < max_retries - 1:
                    print(f'[Raakaadee] 🔄 Retry {attempt + 2}/{max_retries}...', file=sys.stderr)
                    continue
                return None

            page.wait_for_timeout(2000)
            final_text = safe_evaluate(page, '() => document.body ? document.body.innerText : ""')
            if final_text and len(final_text) > 100:
                return final_text
            else:
                if attempt < max_retries - 1:
                    print(f'[Raakaadee] ⚠️ Page too short, retry {attempt + 2}/{max_retries}...', file=sys.stderr)
                    continue
                return None

        except Exception as e:
            print(f'[Raakaadee] ⚠️ Load error: {str(e)[:100]}', file=sys.stderr)
            if attempt < max_retries - 1:
                print(f'[Raakaadee] 🔄 Retry {attempt + 2}/{max_retries}...', file=sys.stderr)
                page.wait_for_timeout(3000)
            else:
                return None

    return None


def scrape_raakaadee(debug=False, target_slug=None, target_url=None):
    """Main scraper — visits multiple category pages"""
    print('[Raakaadee] Starting Camoufox scraper v3...', file=sys.stderr)

    try:
        from camoufox.sync_api import Camoufox
    except ImportError:
        print('[Raakaadee] ERROR: camoufox not installed.', file=sys.stderr)
        return {'success': False, 'error': 'camoufox not installed', 'results': []}

    today = datetime.now().strftime('%Y-%m-%d')
    yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')

    # If specific URL given, use only that
    urls = [target_url] if target_url else CATEGORY_URLS

    try:
        with Camoufox(headless=True) as browser:
            page = browser.new_page()
            all_results = []
            found_slugs = set()

            for url in urls:
                try:
                    page_text = load_page(page, url, debug)
                    if not page_text:
                        continue

                    lines = page_text.split('\n')

                    if debug:
                        # Count "ตรวจผล" lines
                        result_lines = [l for l in lines if 'ตรวจผล' in l]
                        print(f'[Raakaadee] 📄 {len(result_lines)} result sections on this page', file=sys.stderr)

                    # Parse results
                    new_results = parse_page_results(lines, found_slugs, today, yesterday, debug)
                    all_results.extend(new_results)

                    # Also handle Thai govt lottery on main page
                    if 'thai' not in found_slugs and 'raakaadee.com/' == url.split('/')[-1] + '/' or url.endswith('.com/'):
                        thai_match = re.search(r'รางวัลที่\s*1[^\d]*(\d{6})', page_text)
                        thai_bottom = re.search(r'เลขท้าย\s*2\s*ตัว[^\d]*(\d{2})', page_text)
                        if thai_match:
                            prize = thai_match.group(1)
                            three_top = prize[-3:]
                            two_bottom = thai_bottom.group(1) if thai_bottom else ''
                            all_results.append({
                                'slug': 'thai',
                                'lottery_name': 'สลากกินแบ่งรัฐบาล',
                                'first_prize': prize,
                                'three_top': three_top,
                                'two_top': three_top[-2:],
                                'two_bottom': two_bottom,
                                'draw_date': today,
                                'source': 'raakaadee.com',
                            })
                            found_slugs.add('thai')
                            print(f'[Raakaadee]   ✅ สลากกินแบ่งรัฐบาล: {prize} → 3บน={three_top} 2ล่าง={two_bottom}', file=sys.stderr)

                    print(f'[Raakaadee] 📊 Running total: {len(all_results)} results', file=sys.stderr)

                except Exception as e:
                    print(f'[Raakaadee] ❌ Error on {url}: {str(e)[:120]}', file=sys.stderr)
                    continue

            # Filter by slug if specified
            if target_slug:
                all_results = [r for r in all_results if r['slug'] == target_slug]

            # Summary
            print(f'[Raakaadee] ════════════════════════════════', file=sys.stderr)
            print(f'[Raakaadee] 📊 TOTAL: {len(all_results)} results from {len(urls)} pages', file=sys.stderr)
            print(f'[Raakaadee] 📋 Slugs: {", ".join(sorted(found_slugs))}', file=sys.stderr)

            all_system_slugs = set(LOTTERY_MAPPINGS.values())
            missing = all_system_slugs - found_slugs
            if missing:
                print(f'[Raakaadee] ❌ Not found ({len(missing)}): {", ".join(sorted(missing))}', file=sys.stderr)

            return {
                'success': True,
                'results': all_results,
                'scraped_at': datetime.now().isoformat(),
                'pages_scraped': len(urls),
            }

    except Exception as e:
        print(f'[Raakaadee] ❌ Error: {e}', file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        return {'success': False, 'error': str(e), 'results': []}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Scrape lottery results from raakaadee.com')
    parser.add_argument('--debug', action='store_true', help='Enable debug output')
    parser.add_argument('--slug', type=str, help='Filter by specific slug')
    parser.add_argument('--url', type=str, help='Scrape only this URL')
    args = parser.parse_args()

    result = scrape_raakaadee(debug=args.debug, target_slug=args.slug, target_url=args.url)
    print(json.dumps(result, ensure_ascii=False))

    if not result['success']:
        sys.exit(1)
