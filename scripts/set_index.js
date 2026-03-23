/**
 * SET & SET50 Index Scraper (Lightweight - No Puppeteer)
 * ดึง SET Index, SET50, และ Change จาก Google Finance
 * 
 * Usage: node scripts/set_index.js
 * Output: JSON { success: true, set: 1401.81, set_change: 5.23, set50: 950.47 }
 * 
 * กติกาหวยหุ้นไทย (รอบปิดเที่ยง/ปิดเย็น):
 *   3 ตัวบน = ทศนิยมตัวสุดท้ายของ SET50 + ทศนิยม 2 ตัวของ SET
 *   2 ตัวบน = ทศนิยม 2 ตัวของ SET
 *   2 ตัวล่าง = ทศนิยม 2 ตัวของ Change ของ SET (เอาค่าสัมบูรณ์)
 */

import https from 'https';
import zlib from 'zlib';

function fetchPage(url, maxRedirects = 5) {
    return new Promise((resolve, reject) => {
        if (maxRedirects <= 0) return reject(new Error('Too many redirects'));

        const timeout = setTimeout(() => reject(new Error('Timeout after 20s')), 20000);

        const req = https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language': 'en-US,en;q=0.9',
                'Accept-Encoding': 'gzip, deflate, br',
            },
        }, (res) => {
            // Follow redirects
            if ([301, 302, 303, 307, 308].includes(res.statusCode) && res.headers.location) {
                clearTimeout(timeout);
                const redirectUrl = res.headers.location.startsWith('http')
                    ? res.headers.location
                    : new URL(res.headers.location, url).href;
                resolve(fetchPage(redirectUrl, maxRedirects - 1));
                return;
            }

            const chunks = [];
            let stream = res;

            // Handle compression
            const encoding = res.headers['content-encoding'];
            if (encoding === 'gzip') {
                stream = res.pipe(zlib.createGunzip());
            } else if (encoding === 'deflate') {
                stream = res.pipe(zlib.createInflate());
            } else if (encoding === 'br') {
                stream = res.pipe(zlib.createBrotliDecompress());
            }

            stream.on('data', (chunk) => chunks.push(chunk));
            stream.on('end', () => {
                clearTimeout(timeout);
                resolve(Buffer.concat(chunks).toString('utf-8'));
            });
            stream.on('error', (err) => {
                clearTimeout(timeout);
                reject(new Error('Stream error: ' + (err.message || err)));
            });
        });

        req.on('error', (err) => {
            clearTimeout(timeout);
            reject(new Error('Request error: ' + (err.message || err)));
        });
    });
}

async function fetchWithRetry(url, retries = 2) {
    for (let i = 0; i <= retries; i++) {
        try {
            return await fetchPage(url);
        } catch (e) {
            if (i === retries) throw e;
            // Wait 1 second before retry
            await new Promise(r => setTimeout(r, 1000));
        }
    }
}

function parseValue(html) {
    // Method 1: data-last-price attribute
    let match = html.match(/data-last-price="([\d,]+\.?\d*)"/);
    if (match) {
        const v = parseFloat(match[1].replace(/,/g, ''));
        if (v > 0) return v;
    }

    // Method 2: YMlKec class (displayed price)
    match = html.match(/class="YMlKec fxKbKc"[^>]*>([\d,]+\.\d{2})</);
    if (match) {
        const v = parseFloat(match[1].replace(/,/g, ''));
        if (v > 0) return v;
    }

    return null;
}

function parseChange(html) {
    // Strategy 1: Find the FULL opening tag that has data-last-price,
    // then extract data-price-change from that same tag
    const tagMatches = html.match(/<[^>]*data-last-price="[\d,.]+"[^>]*>/g);
    if (tagMatches) {
        for (const tag of tagMatches) {
            const changeMatch = tag.match(/data-price-change="(-?[\d,]+\.?\d*)"/);
            if (changeMatch) {
                const v = parseFloat(changeMatch[1].replace(/,/g, ''));
                if (Math.abs(v) < 200) return v;
            }
        }
    }

    // Strategy 2: All standalone data-price-change attributes
    const allChanges = [...html.matchAll(/data-price-change="(-?[\d,]+\.?\d*)"/g)];
    for (const m of allChanges) {
        const v = parseFloat(m[1].replace(/,/g, ''));
        if (Math.abs(v) < 200) return v;
    }

    // Strategy 3: Look for change display patterns in rendered text
    // Google Finance shows change like: >−2.26</span> or >+5.23<
    const changePatterns = [
        /class="[^"]*P2Luy[^"]*"[^>]*>[\s]*([+\-−]?[\d,]+\.\d{2})/g,
        /class="[^"]*JwB6zf[^"]*"[^>]*>[\s]*([+\-−]?[\d,]+\.\d{2})/g,
        />([+\-−]\d{1,3}\.\d{2})<\//g,
    ];

    for (const pattern of changePatterns) {
        const matches = [...html.matchAll(pattern)];
        for (const m of matches) {
            const raw = m[1].replace(/−/g, '-').replace(/,/g, '');
            const v = parseFloat(raw);
            if (!isNaN(v) && Math.abs(v) < 200 && Math.abs(v) > 0) return v;
        }
    }

    return null;
}

async function main() {
    try {
        let setHtml, set50Html;

        try {
            setHtml = await fetchWithRetry('https://www.google.com/finance/quote/SET:INDEXBKK');
        } catch (e) {
            console.log(JSON.stringify({ success: false, error: 'Cannot fetch SET: ' + e.message }));
            return;
        }

        try {
            set50Html = await fetchWithRetry('https://www.google.com/finance/quote/SET50:INDEXBKK');
        } catch (e) {
            console.log(JSON.stringify({ success: false, error: 'Cannot fetch SET50: ' + e.message }));
            return;
        }

        const setValue = parseValue(setHtml);
        const setChange = parseChange(setHtml);
        const set50Value = parseValue(set50Html);

        if (!setValue) {
            console.log(JSON.stringify({ success: false, error: 'Cannot parse SET value from HTML' }));
            return;
        }

        if (set50Value === null) {
            console.log(JSON.stringify({ success: false, error: 'Cannot parse SET50 value from HTML' }));
            return;
        }

        const result = {
            success: true,
            set: setValue,
            set_change: setChange !== null ? setChange : 0,
            set50: set50Value,
        };

        if (setChange === null) {
            result.change_warning = 'Could not parse change, defaulting to 0';
        }

        console.log(JSON.stringify(result));

    } catch (error) {
        console.log(JSON.stringify({ success: false, error: error.message }));
    }
}

main();
