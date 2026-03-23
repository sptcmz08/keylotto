/**
 * hanoi_normal_api.js
 * Scraper สำหรับหวยฮานอยปกติ (XSMB - Xổ Số Miền Bắc / เวียดนามเหนือ)
 * Source: az24.vn (parse เลขถูกต้อง แต่วันที่อาจคลาดเคลื่อน)
 * ใช้ Node.js https module ล้วนๆ — ไม่ต้องการ Puppeteer/npm install
 *
 * ⚠️ az24.vn มี bug เรื่องวันที่ (อาจแสดงวันก่อนหน้า)
 *    → script จะใช้วันที่ปัจจุบัน ICT (Asia/Bangkok) แทนเสมอ
 *
 * Usage: node scripts/hanoi_normal_api.js
 * Output (stdout JSON):
 * { "success": true, "date": "2026-02-21", "four_digit": "4413",
 *   "three_top": "413", "two_top": "13", "two_bottom": "91" }
 */

import https from 'https';

// ── Today's date in ICT (Asia/Bangkok, UTC+7) ──

function todayICT() {
    const now = new Date();
    const ict = new Date(now.getTime() + (7 * 60 * 60 * 1000));
    return ict.toISOString().slice(0, 10); // "YYYY-MM-DD"
}

// ── HTTP fetch helper ──

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        const req = https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language': 'vi-VN,vi;q=0.9',
                'Referer': 'https://az24.vn/',
            },
        }, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                fetchPage(res.headers.location).then(resolve).catch(reject);
                return;
            }
            let data = '';
            res.setEncoding('utf8');
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve({ status: res.statusCode, body: data }));
        });
        req.on('error', reject);
        req.setTimeout(15000, () => { req.destroy(); reject(new Error('Request timeout')); });
    });
}

// ── Parse XSMB numbers from az24.vn HTML ──
// Index 0 = ĐB (Giải Đặc Biệt, 5 digits) → เอา 4 ตัวท้าย = first_prize
// Index 1 = G1 (Giải Nhất, 5 digits) → เอา 2 ตัวท้าย = two_bottom

function parseNumbers(html) {
    const numbers = [];

    // Strategy 1: Extract spans inside td with class containing "v-giai"
    const vgiaiPattern = /<td[^>]*class="[^"]*v-giai[^"]*"[^>]*>([\s\S]*?)<\/td>/g;
    let tdMatch;
    while ((tdMatch = vgiaiPattern.exec(html)) !== null) {
        const tdContent = tdMatch[1];
        const spanPattern = /<span[^>]*>(\d{2,6})<\/span>/g;
        let spanMatch;
        while ((spanMatch = spanPattern.exec(tdContent)) !== null) {
            numbers.push(spanMatch[1]);
        }
    }

    // Strategy 2: fallback — look for 5-digit or 4-digit numbers in spans
    if (numbers.length === 0) {
        const allSpans = html.match(/<span[^>]*>(\d{4,5})<\/span>/g) || [];
        for (const span of allSpans) {
            const m = span.match(/>(\d{4,5})</);
            if (m) numbers.push(m[1]);
        }
    }

    return numbers;
}

// ── Main ──

async function main() {
    const today = todayICT();
    console.error(`[HanoiNormalApi] Today (ICT): ${today}`);

    try {
        console.error('[HanoiNormalApi] Fetching from az24.vn...');
        const { status, body } = await fetchPage('https://az24.vn/xsmb-sxmb-xo-so-mien-bac.html');

        if (status !== 200) {
            console.log(JSON.stringify({ success: false, error: `HTTP ${status}` }));
            process.exit(1);
        }

        const numbers = parseNumbers(body);

        console.error(`[HanoiNormalApi] Found ${numbers.length} numbers`);
        if (numbers.length > 0) {
            console.error(`[HanoiNormalApi] ĐB=${numbers[0]}, G1=${numbers[1] || '-'}`);
        }

        if (numbers.length === 0) {
            console.log(JSON.stringify({
                success: false,
                error: 'No lottery numbers found on az24.vn',
                pending: true,
            }));
            process.exit(0);
        }

        // Index 0 = ĐB (grand prize, 5 digits)
        // Index 1 = G1 (first prize, 5 digits)
        const grandPrize = numbers[0];
        const g1 = numbers[1] || null;

        if (!grandPrize || grandPrize.length < 4) {
            console.log(JSON.stringify({
                success: false,
                error: 'Grand prize (ĐB) not valid: ' + grandPrize,
                pending: true,
            }));
            process.exit(0);
        }

        // ⚠️ ใช้วันที่ ICT (ไม่ใช้วันที่จาก az24.vn เพราะอาจผิด)
        const result = {
            success: true,
            source: 'az24.vn',
            date: today,
            grand_prize: grandPrize,
            g1: g1,
            four_digit: grandPrize.slice(-4),
            three_top: grandPrize.slice(-3),
            two_top: grandPrize.slice(-2),
            two_bottom: g1 ? g1.slice(-2) : '',
            scraped_at: new Date().toISOString(),
        };

        console.error(`[HanoiNormalApi] ✅ Result: ${result.four_digit} (ĐB=${grandPrize}) date=${today}`);
        console.log(JSON.stringify(result));
        process.exit(0);

    } catch (err) {
        console.error(`[HanoiNormalApi] Error: ${err.message}`);
        console.log(JSON.stringify({ success: false, error: err.message }));
        process.exit(1);
    }
}

main();
