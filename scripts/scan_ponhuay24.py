#!/usr/bin/env python3
"""
ponhuay24.com Lottery Scanner
สแกน ponhuay24.com เพื่อดูว่ามีหวยอะไรบ้าง + ดึงผล

Usage: .venv/bin/python scripts/scan_ponhuay24.py
"""

import sys
import re
import json
from datetime import datetime

# Pages to scan based on Nuxt routes
SCAN_PAGES = [
    ('https://ponhuay24.com/', 'หน้าหลัก'),
    ('https://ponhuay24.com/app/lottolaos', 'หวยลาว'),
    ('https://ponhuay24.com/app/lottohanoi', 'หวยฮานอย'),
    ('https://ponhuay24.com/app/lottovip', 'หวยVIP'),
    ('https://ponhuay24.com/app/lottostock', 'หวยหุ้น'),
    ('https://ponhuay24.com/app/lottoother', 'หวยอื่นๆ'),
    ('https://ponhuay24.com/app/thailotto', 'หวยรัฐบาล'),
    ('https://ponhuay24.com/app/lottodijital', 'หวยดิจิตอล'),
    ('https://ponhuay24.com/app/lottoone', 'หวยone'),
]

# หวยที่เราต้องการหาเพิ่ม
MISSING_LOTTERIES = [
    'ลาวใต้', 'ฮานอยตรุษจีน', 'หวย 12 ราศี', '12 ราศี',
    'ดาวโจนส์สตาร์', 'ดาวโจนส์ STAR',
    'ลาวสันติภาพ', 'ลาวกาชาด', 'ลาวประตูชัย',
]


def main():
    try:
        from camoufox.sync_api import Camoufox
    except ImportError:
        print('ERROR: camoufox not installed', file=sys.stderr)
        sys.exit(1)

    print('=' * 60, file=sys.stderr)
    print('🔍 ponhuay24.com Lottery Scanner', file=sys.stderr)
    print('=' * 60, file=sys.stderr)

    all_text_data = {}

    with Camoufox(headless=True) as browser:
        page = browser.new_page()

        for url, label in SCAN_PAGES:
            print(f'\n🌐 Loading {label} ({url})...', file=sys.stderr)
            try:
                page.goto(url, timeout=30000, wait_until='domcontentloaded')
                # Wait for SPA content to load
                page.wait_for_timeout(5000)

                # Wait for dynamic content
                try:
                    page.wait_for_selector('.ponhuay, .trr, .bg-head, table, [class*="lotto"]', timeout=10000)
                except Exception:
                    pass

                page.wait_for_timeout(3000)

                text = page.evaluate('() => document.body ? document.body.innerText : ""')
                html = page.evaluate('() => document.body ? document.body.innerHTML : ""')

                if not text or len(text) < 50:
                    print(f'  ❌ Page empty or too short ({len(text) if text else 0} chars)', file=sys.stderr)
                    continue

                all_text_data[label] = text
                print(f'  📄 Got {len(text)} chars of text', file=sys.stderr)

                # Look for lottery names and numbers
                lines = text.split('\n')
                lottery_lines = []
                for line in lines:
                    line = line.strip()
                    if not line:
                        continue
                    # Look for 3-digit numbers (lottery results)
                    if re.search(r'\d{3}', line) and len(line) < 200:
                        lottery_lines.append(line)

                # Check if any missing lotteries appear
                for missing in MISSING_LOTTERIES:
                    if missing in text:
                        # Find context around the match
                        idx = text.find(missing)
                        context = text[max(0, idx-50):idx+100].replace('\n', ' | ')
                        print(f'  ✅ Found "{missing}": ...{context}...', file=sys.stderr)

                # Print first 30 relevant lines
                if lottery_lines:
                    print(f'  📊 {len(lottery_lines)} lines with numbers:', file=sys.stderr)
                    for line in lottery_lines[:15]:
                        print(f'    • {line[:120]}', file=sys.stderr)

                # Also try to extract structured data from HTML
                # Look for table rows or specific div patterns
                results = re.findall(r'([\u0e00-\u0e7f\s]+?)[\s:]+(\d{2,6})(?:\s*/\s*(\d{2,3}))?', text)
                if results:
                    print(f'  🎯 Parsed {len(results)} name-number pairs:', file=sys.stderr)
                    for name, num1, num2 in results[:20]:
                        name = name.strip()
                        if len(name) > 3 and len(name) < 40:
                            print(f'    → {name}: {num1} / {num2}', file=sys.stderr)

            except Exception as e:
                print(f'  ❌ Error: {str(e)[:120]}', file=sys.stderr)

    # Final summary
    print('\n' + '=' * 60, file=sys.stderr)
    print('📋 SUMMARY: Missing lottery search results', file=sys.stderr)
    print('=' * 60, file=sys.stderr)

    all_combined = ' '.join(all_text_data.values())
    for missing in MISSING_LOTTERIES:
        if missing in all_combined:
            print(f'  ✅ {missing} — FOUND on ponhuay24.com', file=sys.stderr)
        else:
            print(f'  ❌ {missing} — NOT found', file=sys.stderr)


if __name__ == '__main__':
    main()
