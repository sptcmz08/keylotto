/**
 * Lao Samakki (ລາວສາມັກຄີ) Scraper — Public API Version
 * ดึงผลจาก public-api.laounion.com โดยตรง (ไม่ต้องใช้ Puppeteer/Chrome)
 *
 * Usage: node scripts/laounion_api.js
 *
 * Output: JSON to stdout
 * {
 *   "success": true,
 *   "results": {
 *     "slug": "lao-samakki",
 *     "lottery_name": "ลาวสามัคคี",
 *     "digit5": "45712",
 *     "digit4": "5712",
 *     "three_top": "712",
 *     "two_top": "12",
 *     "two_bottom": "29",
 *     "draw_date": "2026-02-19"
 *   }
 * }
 */

import https from 'https';

const API_URL = 'https://public-api.laounion.com/result';

function fetchJson(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (compatible; LotteryBot/1.0)',
                'Accept': 'application/json',
            },
            timeout: 15000,
        }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve(JSON.parse(data));
                } catch (e) {
                    reject(new Error(`JSON parse error: ${e.message}`));
                }
            });
        }).on('error', reject).on('timeout', () => reject(new Error('Request timeout')));
    });
}

async function scrapeLaoSamakkiApi() {
    console.error('[LaoSamakkiApi] Fetching from public-api.laounion.com...');

    try {
        const json = await fetchJson(API_URL);

        if (json.status !== 'success' || !json.data) {
            console.error('[LaoSamakkiApi] ⚠️ No results yet');
            console.log(JSON.stringify({
                success: false,
                error: 'No results available',
                results: null,
            }));
            return;
        }

        const data = json.data;
        const results = data.results;

        if (!results || !results.digit5) {
            console.error('[LaoSamakkiApi] ⚠️ Results not released yet');
            console.log(JSON.stringify({
                success: false,
                error: 'Results not released yet',
                results: null,
            }));
            return;
        }

        const output = {
            slug: 'lao-samakki',
            lottery_name: 'ลาวสามัคคี',
            digit5: results.digit5,
            digit4: results.digit4,
            first_prize: results.digit5,
            three_top: results.digit3,
            two_top: results.digit2_top,
            two_bottom: results.digit2_bottom || results.digit2_special || '',  // 2ตัวล่าง (digit2_special คือเลขคนละตัว!)
            draw_date: data.lotto_date,
        };

        console.error(`[LaoSamakkiApi] ✅ Result: ${output.digit5} (${output.draw_date})`);
        console.log(JSON.stringify({
            success: true,
            results: output,
            scraped_at: new Date().toISOString(),
        }));

    } catch (error) {
        console.error(`[LaoSamakkiApi] Error: ${error.message}`);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            results: null,
        }));
        process.exit(1);
    }
}

scrapeLaoSamakkiApi();
