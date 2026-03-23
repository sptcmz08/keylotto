#!/usr/bin/env python3
"""Quick debug: dump raw HTML around /result/set to see actual format"""
import sys
from urllib.request import urlopen, Request

url = 'https://exphuay.com/result'
headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Accept': 'text/html,application/xhtml+xml',
}
req = Request(url, headers=headers)
html = urlopen(req, timeout=15).read().decode('utf-8')

# Find /result/set and show 1000 chars around it
slugs = ['set', 'nikkei-morning', 'laopatuxay', 'sgx']
for slug in slugs:
    pattern = f'/result/{slug}'
    pos = html.find(pattern)
    if pos == -1:
        print(f"\n❌ '{pattern}' NOT FOUND in HTML\n")
        continue
    
    # Show context: 100 chars before, 500 chars after  
    start = max(0, pos - 100)
    end = min(len(html), pos + 500)
    chunk = html[start:end]
    
    print(f"\n{'='*60}")
    print(f"Found '{pattern}' at position {pos}")
    print(f"{'='*60}")
    print(chunk)
    print(f"{'='*60}\n")
