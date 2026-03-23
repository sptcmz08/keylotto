#!/usr/bin/env python3
"""
GSB & BAAC Lottery Scraper
ดึงผลสลากออมสิน 1 ปี จาก psc.gsb.or.th (เลข 7 หลัก)
ดึงผลสลาก ธกส จาก baac.or.th/content-lotto.php

เลขรางวัลของออมสินเป็น 7 หลัก เช่น 7623395
Format: "งวดที่ 614 M 7623395"

ระบบ cron: ตั้งให้รันทุกวันที่ 16 ของเดือน เวลา 16:30
30 16 16 * * cd /var/www/vhosts/imzshop97.com/httpdocs && .venv/bin/python scripts/scrape_thai_savings.py

Usage:
  .venv/bin/python scripts/scrape_thai_savings.py --debug
  .venv/bin/python scripts/scrape_thai_savings.py --slug gsb --debug
  .venv/bin/python scripts/scrape_thai_savings.py --slug baac --debug
"""

import sys
import re
import json
import argparse
from datetime import datetime


def scrape_gsb(page, debug=False):
    """ดึงผลสลากออมสิน 1 ปี — เลข 7 หลัก"""
    today = datetime.now()

    # ลองงวดปัจจุบันก่อน แล้วย้อนหลัง
    for months_back in range(0, 4):
        month = today.month - months_back
        year = today.year
        while month <= 0:
            month += 12
            year -= 1

        date_str = f'16{month:02d}{year}'
        draw_date = f'{year}-{month:02d}-16'
        url = f'https://psc.gsb.or.th/resultsalak/salak-1year-100/{date_str}'

        if debug:
            print(f'[GSB] 🌐 Trying {url}...', file=sys.stderr)

        try:
            page.goto(url, timeout=30000, wait_until='domcontentloaded')

            # รอ JS render ข้อมูลรางวัล (หน้านี้โหลดเลขผ่าน AJAX)
            page.wait_for_timeout(10000)

            # Scroll ลงเพื่อ trigger lazy loading
            page.evaluate('() => window.scrollTo(0, 500)')
            page.wait_for_timeout(3000)
            page.evaluate('() => window.scrollTo(0, 1000)')
            page.wait_for_timeout(3000)

            text = page.evaluate('() => document.body ? document.body.innerText : ""')

            if debug:
                print(f'[GSB]   📄 {len(text)} chars', file=sys.stderr)

            # Skip if no data
            if 'ไม่มีข้อมูล' in text or len(text) < 80:
                if debug:
                    print(f'[GSB]   ⏭️ ไม่มีข้อมูลงวดนี้', file=sys.stderr)
                continue

            # ======================================
            # เลขรางวัลออมสิน = 7 หลัก
            # Format: "งวดที่ 614 M 7623395"
            # อันดับที่ 1 = รางวัลที่ 1 (10,000,000 บาท)
            # ======================================

            # Strategy 1: หา pattern "งวดที่ XXX [A-Z] NNNNNNN"
            prize_match = re.search(r'งวดที่\s+\d+\s+[A-Z]\s+(\d{7})', text)
            if prize_match:
                first_prize = prize_match.group(1)
                if debug:
                    print(f'[GSB]   ✅ อันดับ 1: {first_prize}', file=sys.stderr)
                return {
                    'slug': 'gsb',
                    'lottery_name': 'ออมสิน',
                    'first_prize': first_prize,
                    'three_top': first_prize[-3:],
                    'two_top': first_prize[-2:],
                    'two_bottom': first_prize[-2:],
                    'draw_date': draw_date,
                    'source': 'psc.gsb.or.th',
                }

            # Strategy 2: หาเลข 7 หลักทั้งหมด
            seven_digits = re.findall(r'\b(\d{7})\b', text)
            if seven_digits:
                first_prize = seven_digits[0]
                if debug:
                    print(f'[GSB]   ✅ Found 7-digit: {first_prize}', file=sys.stderr)
                return {
                    'slug': 'gsb',
                    'lottery_name': 'ออมสิน',
                    'first_prize': first_prize,
                    'three_top': first_prize[-3:],
                    'two_top': first_prize[-2:],
                    'two_bottom': first_prize[-2:],
                    'draw_date': draw_date,
                    'source': 'psc.gsb.or.th',
                }

            if debug:
                # Dump full text for debugging
                print(f'[GSB]   ⚠️ No 7-digit found. Full text:', file=sys.stderr)
                print(f'{text}', file=sys.stderr)

        except Exception as e:
            if debug:
                print(f'[GSB]   ❌ Error: {str(e)[:150]}', file=sys.stderr)
            continue

    print(f'[GSB] ⚠️ ไม่พบผลรางวัลออมสิน', file=sys.stderr)
    return None


def scrape_baac(page, debug=False):
    """ดึงผลสลาก ธกส จาก baac.or.th/salak/content-lotto.php
    
    ขั้นตอน:
    1. เข้าหน้า baac.or.th/salak/content-lotto.php
    2. Dropdown จะมีวันที่ออกรางวัล (16 มีนาคม 2569, 16 กุมภาพันธ์ 2569, ...)
    3. เลือกตัวแรก (งวดล่าสุด) → กดปุ่ม "ตรวจ"
    4. อ่านเลข 7 หลักจากตารางผล — "รางวัลที่ 1" = เลขรางวัลใหญ่
    """

    url = 'https://www.baac.or.th/salak/content-lotto.php'

    if debug:
        print(f'[BAAC] 🌐 Loading {url}...', file=sys.stderr)

    try:
        page.goto(url, timeout=30000, wait_until='domcontentloaded')
        page.wait_for_timeout(8000)

        text = page.evaluate('() => document.body ? document.body.innerText : ""')
        if debug:
            print(f'[BAAC]   📄 {len(text)} chars after initial load', file=sys.stderr)

        # หา dropdown วันที่ (select element)
        select = page.query_selector('select')
        if select:
            # เลือก option แรก (งวดล่าสุด) — มันถูกเลือกอยู่แล้วปกติ
            options = page.query_selector_all('select option')
            if options and debug:
                first_text = options[0].inner_text()
                print(f'[BAAC]   📅 Found {len(options)} options, first: {first_text}', file=sys.stderr)

            # เลือก option แรกอีกที เพื่อให้แน่ใจ
            if options:
                first_value = options[0].get_attribute('value')
                if first_value:
                    select.select_option(value=first_value)
                    if debug:
                        print(f'[BAAC]   Selected value: {first_value}', file=sys.stderr)
        else:
            if debug:
                print(f'[BAAC]   ⚠️ No select dropdown found', file=sys.stderr)

        # กดปุ่ม "ตรวจ"
        submit_btn = page.query_selector('input[type="submit"], button[type="submit"]')
        if not submit_btn:
            # ลองหาจาก text
            submit_btn = page.query_selector('input[value="ตรวจ"], button:has-text("ตรวจ")')
        
        if submit_btn:
            if debug:
                print(f'[BAAC]   🔘 Clicking ตรวจ button...', file=sys.stderr)
            submit_btn.click()
            page.wait_for_timeout(8000)
        else:
            if debug:
                print(f'[BAAC]   ⚠️ No submit button found, page may auto-display', file=sys.stderr)

        # Scroll ลงเพื่อดูผล
        page.evaluate('() => window.scrollTo(0, 500)')
        page.wait_for_timeout(3000)

        # อ่านเนื้อหาหลังจากกดตรวจ
        text = page.evaluate('() => document.body ? document.body.innerText : ""')

        if debug:
            print(f'[BAAC]   📄 {len(text)} chars after submit', file=sys.stderr)

        # หาเลข 7 หลัก — รางวัลที่ 1 จะเป็นตัวแรก
        seven_digits = re.findall(r'\b(\d{7})\b', text)

        if debug:
            print(f'[BAAC]   7-digit numbers: {seven_digits[:10]}', file=sys.stderr)
            if not seven_digits:
                print(f'[BAAC]   FULL TEXT:\n{text[:2000]}', file=sys.stderr)

        if seven_digits:
            # ข้าม number ที่อาจเป็น date/year → เอาตัวแรกที่ไม่ใช่ date
            first_prize = seven_digits[0]
            
            # Extract draw date from text
            draw_date = datetime.now().strftime('%Y-%m-%d')
            date_match = re.search(r'วันที่\s+(\d{1,2})\s+\S+\s+(\d{4})', text)
            if date_match:
                day = int(date_match.group(1))
                thai_year = int(date_match.group(2))
                ce_year = thai_year - 543
                month = datetime.now().month
                draw_date = f'{ce_year}-{month:02d}-{day:02d}'

            if debug:
                print(f'[BAAC]   ✅ รางวัลที่ 1: {first_prize}', file=sys.stderr)

            return {
                'slug': 'baac',
                'lottery_name': 'ธกส',
                'first_prize': first_prize,
                'three_top': first_prize[-3:],
                'two_top': first_prize[-2:],
                'two_bottom': first_prize[-2:],
                'draw_date': draw_date,
                'source': 'baac.or.th',
            }

    except Exception as e:
        if debug:
            print(f'[BAAC]   ❌ Error: {str(e)[:200]}', file=sys.stderr)

    print(f'[BAAC] ⚠️ ไม่พบผลรางวัล ธกส', file=sys.stderr)
    return None


def main(debug=False, target_slug=None):
    """Main scraper"""
    print('[ThaiSavings] Starting Camoufox scraper...', file=sys.stderr)

    try:
        from camoufox.sync_api import Camoufox
    except ImportError:
        print('[ThaiSavings] ERROR: camoufox not installed', file=sys.stderr)
        return {'success': False, 'error': 'camoufox not installed', 'results': []}

    all_results = []

    try:
        with Camoufox(headless=True) as browser:
            page = browser.new_page()

            if not target_slug or target_slug == 'gsb':
                result = scrape_gsb(page, debug)
                if result:
                    all_results.append(result)
                    print(f'[ThaiSavings] ✅ ออมสิน: {result["first_prize"]}', file=sys.stderr)

            if not target_slug or target_slug == 'baac':
                result = scrape_baac(page, debug)
                if result:
                    all_results.append(result)
                    print(f'[ThaiSavings] ✅ ธกส: {result["first_prize"]}', file=sys.stderr)

        print(f'\n[ThaiSavings] 📊 TOTAL: {len(all_results)} results', file=sys.stderr)

        return {
            'success': len(all_results) > 0,
            'results': all_results,
            'scraped_at': datetime.now().isoformat(),
        }

    except Exception as e:
        print(f'[ThaiSavings] ❌ Fatal error: {str(e)[:200]}', file=sys.stderr)
        return {'success': False, 'error': str(e)[:200], 'results': []}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='GSB & BAAC Savings Lottery Scraper')
    parser.add_argument('--debug', action='store_true')
    parser.add_argument('--slug', type=str, default=None, choices=['gsb', 'baac'])
    args = parser.parse_args()

    result = main(debug=args.debug, target_slug=args.slug)
    print(json.dumps(result, ensure_ascii=False, indent=2))
