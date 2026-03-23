/**
 * ManyCai Draw Page Scraper (Primary Source)
 * ดึงผลหวยล่าสุดทุกประเภทจากหน้า /Issue/draw
 * 
 * Usage: node scripts/manycai_draw.js
 */

import { addExtra } from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import puppeteerCore from 'puppeteer';

const puppeteer = addExtra(puppeteerCore);
puppeteer.use(StealthPlugin());

// Mapping ชื่อหวยภาษาไทย -> lottery_type info (ต้องตรงกับ DB)
const LOTTERY_NAME_MAPPINGS = {
    // === หวยรัฐบาล/ออมทรัพย์ ===
    'หวยรัฐบาล': { code: 'TGFC', slug: 'thai', digitCount: 6 },
    'สลากออมทรัพย์ ธ.ก.ส.': { code: 'BAAC', slug: 'baac', digitCount: 6 },

    // === หวยลาว ===
    'หวยลาว': { code: 'TLZC', slug: 'lao', digitCount: 4 },
    'หวยลาวพัฒนา': { code: 'TLZC', slug: 'lao', digitCount: 4 },
    // 'จับยี่กี VIP': { code: 'ZCVIP', slug: 'lao-vip', digitCount: 5 }, // ❌ คนละตัวกับ หวยลาว VIP
    // 'หวยลาว VIP': { code: 'ZCVIP', slug: 'lao-vip', digitCount: 5 }, // ❌ ย้ายไปใช้ ScrapeLaoVip (laos-vip.com)

    // === หวยฮานอย ===
    'หวยฮานอย': { code: 'YNHN', slug: 'hanoi', digitCount: 4 },
    'หวยฮานอย VIP': { code: 'HNVIP', slug: 'hanoi-vip', digitCount: 5 },
    'หวยฮานอยพิเศษ': { code: 'BFHN', slug: 'hanoi-special', digitCount: 5 },
    'ฮานอยพิเศษ': { code: 'BFHN', slug: 'hanoi-special', digitCount: 5 },
    'ฮานอยเฉพาะกิจ': { code: 'CQHN', slug: 'hanoi-adhoc', digitCount: 5 },

    // === หวยมาเลย์ ===
    // ❌ ManyCai YNMA ไม่ใช่ Magnum 4D → ใช้ ScrapeMalayLottery (4dresult88.com) แทน
    // 'หวยมาเลย์': { code: 'YNMA', slug: 'malay', digitCount: 4 },

    // === หุ้นนิเคอิ (ญี่ปุ่น) ===
    'นิเคอิ - รอบเช้า': { code: 'GSJPA', slug: 'nikkei-morning', digitCount: 3 },
    'นิเคอิ - รอบบ่าย': { code: 'GSJPP', slug: 'nikkei-afternoon', digitCount: 3 },

    // === หุ้นจีน ===
    'หุ้นจีน - รอบเช้า': { code: 'GSCNA', slug: 'china-morning', digitCount: 3 },
    'หุ้นจีน - รอบบ่าย': { code: 'GSCNP', slug: 'china-afternoon', digitCount: 3 },

    // === หุ้นฮั่งเส็ง (ฮ่องกง) ===
    'หุ้นฮั่งเส็ง - รอบเช้า': { code: 'GSHKA', slug: 'hangseng-morning', digitCount: 3 },
    'หุ้นฮั่งเส็ง - รอบบ่าย': { code: 'GSHKP', slug: 'hangseng-afternoon', digitCount: 3 },

    // === หุ้นไต้หวัน ===
    'หุ้นไต้หวัน': { code: 'GSTW', slug: 'taiwan', digitCount: 3 },

    // === หุ้นเกาหลี ===
    'หุ้นเกาหลี': { code: 'GSKR', slug: 'korea', digitCount: 3 },

    // === หุ้นสิงคโปร์ ===
    'หุ้นสิงคโปร์': { code: 'GSSG', slug: 'singapore', digitCount: 3 },

    // === หุ้นไทย ===
    'หุ้นไทย': { code: 'GSTH', slug: 'thai-stock', digitCount: 3 },
    'หุ้นไทย - รอบเช้า': { code: 'GSTH', slug: 'thai-stock-morning', digitCount: 3 },
    'หุ้นไทย - รอบบ่าย': { code: 'GSTH', slug: 'thai-stock', digitCount: 3 },

    // === หุ้นอินเดีย ===
    'หุ้นอินเดีย': { code: 'GSIN', slug: 'india', digitCount: 3 },

    // === หุ้นอียิปต์ ===
    'หุ้นอียิปต์': { code: 'GSEG', slug: 'egypt', digitCount: 3 },

    // === หุ้นรัสเซีย ===
    'หุ้นรัสเซีย': { code: 'GSRU', slug: 'russia', digitCount: 3 },

    // === หุ้นเยอรมัน ===
    'หุ้นเยอรมัน': { code: 'GSDE', slug: 'germany', digitCount: 3 },

    // === หุ้นอังกฤษ ===
    'หุ้นอังกฤษ': { code: 'GSUK', slug: 'uk', digitCount: 3 },

    // === หุ้นดาวโจนส์ ===
    'หุ้นดาวโจนส์': { code: 'GSUS', slug: 'dowjones', digitCount: 3 },
};

async function scrapeDrawPage() {
    const url = 'https://th.manycai.com/Issue/draw';

    console.error('[ManyCai Draw] Starting scrape...');

    let browser;
    try {
        // Auto-discover Chrome executable path
        const fs = await import('fs');
        const path = await import('path');
        const { execSync } = await import('child_process');

        let executablePath = null;

        // Method 1: Search Puppeteer cache directories dynamically
        const cacheRoots = [
            // Project-level cache
            path.resolve(process.cwd(), '.puppeteer-cache'),
            path.resolve(process.cwd(), 'node_modules/puppeteer/.local-chromium'),
            // Home directory cache
            path.resolve(process.env.HOME || '/root', '.cache/puppeteer'),
            // Server-specific paths
            '/var/www/vhosts/after-spa.com/lotto.after-spa.com/.puppeteer-cache',
            '/root/.cache/puppeteer',
        ];

        for (const cacheRoot of cacheRoots) {
            if (!fs.existsSync(cacheRoot)) continue;
            // Search for chrome/chromium binary recursively
            try {
                const findCmd = `find "${cacheRoot}" -type f \\( -name "chrome" -o -name "chromium" \\) 2>/dev/null | head -5`;
                const found = execSync(findCmd, { timeout: 5000 }).toString().trim();
                if (found) {
                    const lines = found.split('\n');
                    for (const line of lines) {
                        if (line && fs.existsSync(line)) {
                            executablePath = line;
                            console.error(`[ManyCai Draw] Found Chrome in cache: ${line}`);
                            break;
                        }
                    }
                }
            } catch (e) { /* find command failed, try next */ }
            if (executablePath) break;
        }

        // Method 2: Common system-installed Chrome paths
        if (!executablePath) {
            const systemPaths = [
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
                '/snap/bin/chromium',
                '/usr/local/bin/chrome',
                '/usr/local/bin/chromium',
            ];
            for (const p of systemPaths) {
                if (fs.existsSync(p)) {
                    executablePath = p;
                    console.error(`[ManyCai Draw] Found Chrome at system path: ${p}`);
                    break;
                }
            }
        }

        // Method 3: Use `which` command
        if (!executablePath) {
            try {
                const which = execSync('which google-chrome chromium chromium-browser 2>/dev/null').toString().trim();
                if (which) {
                    executablePath = which.split('\n')[0];
                    console.error(`[ManyCai Draw] Found Chrome via which: ${executablePath}`);
                }
            } catch (e) { /* which command failed */ }
        }

        if (executablePath) {
            console.error(`[ManyCai Draw] Using Chrome: ${executablePath}`);
        } else {
            console.error('[ManyCai Draw] No Chrome found, letting Puppeteer auto-detect...');
        }

        browser = await puppeteer.launch({
            headless: true,
            executablePath: executablePath || undefined,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--single-process',
                '--disable-blink-features=AutomationControlled',
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1280, height: 800 });

        console.error(`[ManyCai Draw] Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
        // Extra wait for Imperva challenge to resolve
        await new Promise(resolve => setTimeout(resolve, 5000));


        // Build digitCount lookup from LOTTERY_NAME_MAPPINGS
        const digitCountMap = {};
        for (const [name, info] of Object.entries(LOTTERY_NAME_MAPPINGS)) {
            digitCountMap[name] = info.digitCount || 6;
        }

        // Extract all data from table including codelist attribute
        const results = await page.evaluate((digitCounts) => {
            const rows = document.querySelectorAll('table tr');
            const data = [];

            const thaiMonths = {
                'มกราคม': '01', 'กุมภาพันธ์': '02', 'มีนาคม': '03',
                'เมษายน': '04', 'พฤษภาคม': '05', 'มิถุนายน': '06',
                'กรกฎาคม': '07', 'สิงหาคม': '08', 'กันยายน': '09',
                'ตุลาคม': '10', 'พฤศจิกายน': '11', 'ธันวาคม': '12'
            };

            rows.forEach((row, index) => {
                if (index === 0) return; // Skip header

                const cells = row.querySelectorAll('td');
                if (cells.length < 4) return;

                const lotteryName = cells[0]?.innerText?.trim();

                // Get first_prize from column 1 (number circles)
                const numberSpans = cells[1]?.querySelectorAll('span');
                let numbers = [];
                if (numberSpans) {
                    numberSpans.forEach(span => {
                        const num = span.innerText?.trim();
                        if (/^\d$/.test(num)) numbers.push(num);
                    });
                }
                const firstPrizeRaw = numbers.join('');

                // Truncate to expected digit count based on lottery name
                const expectedDigits = digitCounts[lotteryName] || 6;
                const firstPrize = firstPrizeRaw.length > expectedDigits
                    ? firstPrizeRaw.slice(0, expectedDigits)
                    : firstPrizeRaw;

                // Parse date from column 2
                const dateText = cells[2]?.innerText?.trim();
                let isoDate = null;
                const dateMatch = dateText?.match(/(\d{1,2})\s+(\S+)\s+(\d{4})/);
                if (dateMatch) {
                    const day = dateMatch[1].padStart(2, '0');
                    const month = thaiMonths[dateMatch[2]] || '01';
                    let year = parseInt(dateMatch[3]);
                    if (year > 2500) year = year - 543;
                    isoDate = `${year}-${month}-${day}`;
                }

                // Reject future dates (prevent bad data)
                if (isoDate) {
                    const today = new Date();
                    const drawDateObj = new Date(isoDate);
                    if (drawDateObj > today) {
                        return; // Skip future-dated results
                    }
                }

                // Validate first_prize length matches expected digits
                if (firstPrize.length !== expectedDigits) {
                    return; // Skip if digit count doesn't match (corrupt data)
                }

                // Get codelist from any cell (ดูรายละเอียด link)
                // Format: codelist='{"code":"0,2,9","code1":"9,5"}'
                // code = full number (3 ตัวบน), code1 = 2 ตัวล่าง
                let codeDigits = '';
                let code1Digits = '';
                // Search the ENTIRE row for codelist attribute (not just cells[3])
                const detailLink = row.querySelector('a[codelist]') || row.querySelector('[codelist]');
                if (detailLink) {
                    try {
                        const codelist = JSON.parse(detailLink.getAttribute('codelist'));
                        if (codelist.code) {
                            codeDigits = codelist.code.replace(/,/g, '');
                        }
                        if (codelist.code1) {
                            code1Digits = codelist.code1.replace(/,/g, '');
                        }
                    } catch (e) { /* ignore parse error */ }
                }

                // Always take last 3 digits for three_top
                const threeTop = (codeDigits || firstPrize).slice(-3);
                // two_bottom: from codelist.code1, or for 4+ digit lotteries = first 2 digits
                let twoBottom = code1Digits ? code1Digits.slice(-2) : '';
                if (!twoBottom && firstPrize.length >= 4) {
                    twoBottom = firstPrize.slice(0, 2);
                }

                if (lotteryName && firstPrize && /^\d+$/.test(firstPrize)) {
                    data.push({
                        lottery_name: lotteryName,
                        first_prize: firstPrize,
                        draw_date: isoDate,
                        date_thai: dateText,
                        three_top: threeTop,
                        two_top: threeTop.slice(-2),
                        two_bottom: twoBottom,
                    });
                }
            });

            return data;
        }, digitCountMap);

        console.error(`[ManyCai Draw] Found ${results.length} lottery results`);

        return {
            success: true,
            source: 'draw',
            scrapedAt: new Date().toISOString(),
            count: results.length,
            results: results
        };

    } catch (error) {
        console.error(`[ManyCai Draw] Error: ${error.message}`);
        return {
            success: false,
            error: error.message,
            scrapedAt: new Date().toISOString(),
            results: []
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Run if called directly
const result = await scrapeDrawPage();
console.log(JSON.stringify(result, null, 2));
