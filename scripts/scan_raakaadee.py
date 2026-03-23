#!/usr/bin/env python3
"""
Raakaadee.com Full Lottery Scanner
สแกนทุกหน้าบน raakaadee.com เพื่อดูว่ามีหวยอะไรบ้าง
แล้ว match กับรายชื่อที่ระบบต้องการ

Usage: .venv/bin/python scripts/scan_raakaadee.py
"""

import sys
import re
from datetime import datetime

# === รายชื่อหวยที่ระบบต้องการ (จาก masterlot999) ===
REQUIRED_LOTTERIES = [
    # 🟢 เปิดทุกวัน
    'ลาวประตูชัย', 'ลาวสันติภาพ', 'ประชาชนลาว', 'ลาว EXTRA',
    'นิเคอิเช้า VIP', 'ฮานอยอาเซียน', 'จีนเช้า VIP', 'ลาว TV',
    'ฮั่งเส็งเช้า VIP', 'ฮานอย HD', 'ไต้หวัน VIP', 'ฮานอยสตาร์',
    'เกาหลี VIP', 'นิเคอิบ่าย VIP', 'ลาว HD', 'ฮานอย TV',
    'จีนบ่าย VIP', 'ฮั่งเส็งบ่าย VIP', 'ลาวสตาร์', 'ฮานอยกาชาด',
    'สิงคโปร์ VIP', 'ฮานอยพิเศษ', 'ฮานอยสามัคคี', 'ฮานอยปกติ',
    'ฮานอยตรุษจีน', 'ฮานอย VIP', 'ฮานอยพัฒนา', 'ลาวใต้',
    'ลาวสามัคคี', 'ลาวอาเซียน', 'ลาว VIP', 'ลาวสามัคคี VIP',
    'อังกฤษ VIP', 'ลาวสตาร์ VIP', 'ฮานอย EXTRA', 'เยอรมัน VIP',
    'ลาวกาชาด', 'รัสเซีย VIP', 'ดาวโจนส์ VIP',
    # 🟡 จันทร์-ศุกร์
    'นิเคอิ - เช้า', 'หุ้นจีน - เช้า', 'ฮั่งเส็ง - เช้า',
    'หุ้นไต้หวัน', 'หุ้นเกาหลี', 'นิเคอิ - บ่าย',
    'หุ้นจีน - บ่าย', 'ฮั่งเส็ง - บ่าย', 'หุ้นสิงคโปร์',
    'หุ้นไทย - เย็น', 'หุ้นอินเดีย', 'หุ้นอียิปต์',
    'ลาวพัฒนา', 'หวย 12 ราศี', 'หุ้นอังกฤษ', 'หุ้นเยอรมัน',
    'หุ้นรัสเซีย', 'ดาวโจนส์อเมริกา', 'ดาวโจนส์ STAR', 'หุ้นดาวโจนส์',
    # 🔴 หวยไทย
    'รัฐบาลไทย', 'ออมสิน', 'ธกส',
]

# === ชื่อใน masterlot999 → ชื่อที่อาจปรากฏบน raakaadee ===
ALTERNATE_NAMES = {
    'นิเคอิ - เช้า': ['หุ้นนิเคอิเช้า', 'นิเคอิเช้า', 'นิเคอิ - รอบเช้า'],
    'นิเคอิ - บ่าย': ['หุ้นนิเคอิบ่าย', 'นิเคอิบ่าย', 'นิเคอิ - รอบบ่าย'],
    'หุ้นจีน - เช้า': ['หุ้นจีนเช้า', 'จีนเช้า', 'หุ้นจีน - รอบเช้า'],
    'หุ้นจีน - บ่าย': ['หุ้นจีนบ่าย', 'จีนบ่าย', 'หุ้นจีน - รอบบ่าย'],
    'ฮั่งเส็ง - เช้า': ['หุ้นฮั่งเส็งเช้า', 'ฮั่งเส็งเช้า', 'หุ้นฮั่งเส็ง - รอบเช้า'],
    'ฮั่งเส็ง - บ่าย': ['หุ้นฮั่งเส็งบ่าย', 'ฮั่งเส็งบ่าย', 'หุ้นฮั่งเส็ง - รอบบ่าย'],
    'หุ้นไทย - เย็น': ['หุ้นไทย', 'หุ้นไทยเย็น'],
    'ฮานอย EXTRA': ['ฮานอย Extra', 'ฮานอยEXTRA'],
    'ฮานอย TV': ['ฮานอยทีวี', 'ฮานอยTV'],
    'ลาว EXTRA': ['ลาว Extra', 'ลาวEXTRA'],
    'ลาว TV': ['ลาวทีวี', 'ลาวTV'],
    'ลาว HD': ['ลาวHD'],
    'ฮานอย HD': ['ฮานอยHD'],
    'นิเคอิเช้า VIP': ['นิเคอิเช้าVIP'],
    'นิเคอิบ่าย VIP': ['นิเคอิบ่ายVIP'],
    'จีนเช้า VIP': ['จีนเช้าVIP'],
    'จีนบ่าย VIP': ['จีนบ่ายVIP'],
    'ฮั่งเส็งเช้า VIP': ['ฮั่งเส็งเช้าVIP'],
    'ฮั่งเส็งบ่าย VIP': ['ฮั่งเส็งบ่ายVIP'],
    'ไต้หวัน VIP': ['ไต้หวันVIP'],
    'เกาหลี VIP': ['เกาหลีVIP'],
    'ลาว VIP': ['ลาวVIP'],
    'ลาวสามัคคี VIP': ['ลาวสามัคคีVIP'],
    'ลาวสตาร์ VIP': ['ลาวสตาร์VIP'],
    'ฮานอย VIP': ['ฮานอยVIP'],
    'รัฐบาลไทย': ['สลากกินแบ่งรัฐบาล', 'หวยรัฐบาล'],
    'ธกส': ['สลากออมทรัพย์ ธ.ก.ส.', 'ธ.ก.ส.'],
    'ออมสิน': ['สลากออมสิน'],
    'ดาวโจนส์อเมริกา': ['หุ้นดาวโจนส์อเมริกา', 'ดาวโจนส์ อเมริกา'],
    'รัสเซีย VIP': ['รัสเซียVIP', 'หุ้นรัสเซีย VIP'],
    'เยอรมัน VIP': ['เยอรมันVIP', 'หุ้นเยอรมัน VIP'],
    'ดาวโจนส์ VIP': ['ดาวโจนส์VIP', 'หุ้นดาวโจนส์ VIP'],
    'ดาวโจนส์ STAR': ['ดาวโจนส์STAR', 'หุ้นดาวโจนส์ STAR'],
}

# === หน้าที่จะสแกน ===
SCAN_URLS = [
    'https://www.raakaadee.com/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หุ้น-VIP/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยปกติ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอยพิเศษ/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยฮานอย-VIP/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวพัฒนา/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสตาร์/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวประตูชัย/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาวสามัคคี/',
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยลาว-VIP/',
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
    'https://www.raakaadee.com/ตรวจหวย-หุ้น/หวยมาเลย์/',
]


def extract_lottery_name(line):
    """Extract lottery name from ตรวจผล line"""
    # Pattern: "ตรวจผล หวย{NAME} ออก ..." or "ตรวจผล {NAME} ปิด ..."
    m = re.match(r'ตรวจผล\s+(?:หวย)?(.+?)(?:\s+ออก|\s+ปิด)', line.strip())
    if m:
        name = m.group(1).strip()
        # Remove trailing date/time info
        name = re.sub(r'\s*(?:จ\.|อ\.|พ\.|พฤ\.|ศ\.|ส\.|อา\.).*$', '', name)
        return name
    return None


def match_required(raakaadee_name, required_list, alt_names):
    """Check if a raakaadee name matches any required lottery"""
    clean = re.sub(r'\s+', ' ', raakaadee_name.strip())

    for req in required_list:
        req_clean = re.sub(r'\s+', ' ', req.strip())
        # Direct match
        if clean == req_clean or clean in req_clean or req_clean in clean:
            return req

        # Check alternate names
        alts = alt_names.get(req, [])
        for alt in alts:
            alt_clean = re.sub(r'\s+', ' ', alt.strip())
            if clean == alt_clean or clean in alt_clean or alt_clean in clean:
                return req

    return None


def main():
    try:
        from camoufox.sync_api import Camoufox
    except ImportError:
        print('ERROR: camoufox not installed', file=sys.stderr)
        sys.exit(1)

    print('=' * 60, file=sys.stderr)
    print('🔍 Raakaadee.com Full Lottery Scanner', file=sys.stderr)
    print('=' * 60, file=sys.stderr)

    all_lottery_names = set()

    with Camoufox(headless=True) as browser:
        page = browser.new_page()

        for url in SCAN_URLS:
            print(f'\n🌐 Loading {url}...', file=sys.stderr)
            try:
                page.goto(url, timeout=60000)
                page.wait_for_load_state('networkidle', timeout=30000)

                # Wait for Cloudflare
                max_wait = 30
                waited = 0
                while waited < max_wait:
                    text = page.evaluate('() => document.body.innerText')
                    if 'checking your browser' in text.lower() or 'please wait' in text.lower():
                        if waited == 0:
                            print('  ⏳ Cloudflare...', file=sys.stderr)
                        page.wait_for_timeout(3000)
                        waited += 3
                    else:
                        break

                page.wait_for_timeout(2000)
                text = page.evaluate('() => document.body.innerText')
                lines = text.split('\n')

                page_names = set()
                for line in lines:
                    if 'ตรวจผล' in line:
                        name = extract_lottery_name(line)
                        if name:
                            page_names.add(name)

                print(f'  📄 Found {len(page_names)} lottery names', file=sys.stderr)
                for name in sorted(page_names):
                    print(f'    • {name}', file=sys.stderr)

                all_lottery_names.update(page_names)

            except Exception as e:
                print(f'  ❌ Error: {e}', file=sys.stderr)

    # === Match Report ===
    print('\n' + '=' * 60, file=sys.stderr)
    print(f'📊 TOTAL: {len(all_lottery_names)} unique lottery names on raakaadee.com', file=sys.stderr)
    print('=' * 60, file=sys.stderr)

    matched = {}
    unmatched_required = []

    for req in REQUIRED_LOTTERIES:
        found = False
        for rname in all_lottery_names:
            matched_req = match_required(rname, [req], ALTERNATE_NAMES)
            if matched_req:
                matched[req] = rname
                found = True
                break
        if not found:
            unmatched_required.append(req)

    # Raakaadee names not matching any required
    extra_on_raakaadee = []
    for rname in sorted(all_lottery_names):
        m = match_required(rname, REQUIRED_LOTTERIES, ALTERNATE_NAMES)
        if not m:
            extra_on_raakaadee.append(rname)

    print(f'\n✅ MATCHED ({len(matched)}/{len(REQUIRED_LOTTERIES)}):', file=sys.stderr)
    for req, rname in sorted(matched.items()):
        indicator = '  ✅' if req == rname else f'  ✅ (raakaadee: "{rname}")'
        print(f'  {req}{indicator}', file=sys.stderr)

    print(f'\n❌ NOT FOUND on raakaadee ({len(unmatched_required)}):', file=sys.stderr)
    for req in unmatched_required:
        print(f'  ❌ {req}', file=sys.stderr)

    print(f'\n🔵 EXTRA on raakaadee (not in our list) ({len(extra_on_raakaadee)}):', file=sys.stderr)
    for name in extra_on_raakaadee:
        print(f'  🔵 {name}', file=sys.stderr)


if __name__ == '__main__':
    main()
