/**
 * Lao VIP (ຫວຍລາວ VIP) Scraper — Public API Version
 * ดึงผลจาก app.all123th.com โดยตรง (ไม่ต้องใช้ Puppeteer)
 *
 * API: https://app.all123th.com/get-awards/laosviplot
 *
 * Usage: node scripts/laos_vip_api.js
 */

import https from 'https';

const API_URL = 'https://app.all123th.com/get-awards/laosviplot';

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        const reqUrl = new URL(url);
        const options = {
            hostname: reqUrl.hostname,
            path: reqUrl.pathname + reqUrl.search,
            method: 'GET',
            timeout: 15000,
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
                'Accept': 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language': 'th-TH,th;q=0.9,en;q=0.8',
                'Referer': 'https://laos-vip.com/',
                'Origin': 'https://laos-vip.com',
                'X-Requested-With': 'XMLHttpRequest',
                'Connection': 'keep-alive',
            },
        };

        const req = https.request(options, (res) => {
            // Follow redirect if needed
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                console.error(`[LaoVipApi] Redirect → ${res.headers.location}`);
                return fetchJson(res.headers.location).then(resolve).catch(reject);
            }

            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                console.error(`[LaoVipApi] HTTP ${res.statusCode}, Content-Type: ${res.headers['content-type']}`);
                // Check if response is HTML (blocked/redirected)
                if (data.trim().startsWith('<')) {
                    reject(new Error(`Got HTML response instead of JSON (HTTP ${res.statusCode}) — server may be blocking`));
                    return;
                }
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(new Error(`JSON parse error: ${e.message} | Raw: ${data.substring(0, 200)}`));
                }
            });
        });

        req.on('error', reject);
        req.on('timeout', () => {
            req.destroy();
            reject(new Error('Request timeout'));
        });
        req.end();
    });
}

async function scrapeLaoVipApi() {
    console.error('[LaoVipApi] Fetching from app.all123th.com...');

    try {
        const json = await fetchJson(API_URL);

        if (!json || !json.digit5) {
            console.error('[LaoVipApi] ⚠️ No results yet');
            console.log(JSON.stringify({
                success: false,
                error: 'No results available',
                results: null,
            }));
            return;
        }

        // Calculate draw_date: use lotto_date from API, adjust Buddhist year if needed
        let drawDate = json.lotto_date || json.date;
        if (drawDate) {
            const year = parseInt(drawDate.substring(0, 4));
            if (year > 2500) {
                drawDate = (year - 543) + drawDate.substring(4);
            }
        }

        const output = {
            slug: 'lao-vip',
            lottery_name: 'หวยลาว VIP',
            digit5: json.digit5,
            digit4: json.digit4 || json.digit5.slice(-4),
            first_prize: json.digit5,
            three_top: json.digit3_top || json.digit5.slice(-3),
            two_top: json.digit2_top || json.digit5.slice(-2),
            two_bottom: json.digit2_bottom || json.digit5.slice(0, 2),
            draw_date: drawDate,
        };

        console.error(`[LaoVipApi] ✅ Result: ${output.digit5} (${output.draw_date})`);
        console.log(JSON.stringify({
            success: true,
            results: output,
            scraped_at: new Date().toISOString(),
        }));

    } catch (error) {
        console.error(`[LaoVipApi] Error: ${error.message}`);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            results: null,
        }));
        process.exit(1);
    }
}

scrapeLaoVipApi();
