/**
 * ManyCai History Scraper
 * ดึงผลหวยย้อนหลังจากหน้า /Issue/history ของ ManyCai
 * Uses Puppeteer to render JavaScript-loaded content
 * 
 * Usage: node scripts/manycai_history.js [lotteryCode] [maxPages]
 * Example: node scripts/manycai_history.js YNHN 5
 * Example (all): node scripts/manycai_history.js ALL 3
 */

import puppeteer from 'puppeteer';

// All lottery codes matching the system
const ALL_CODES = [
    { code: 'YNHN', slug: 'hanoi', name: 'หวยฮานอย' },
    { code: 'HNVIP', slug: 'hanoi-vip', name: 'หวยฮานอย VIP' },
    { code: 'BFHN', slug: 'hanoi-special', name: 'หวยฮานอยพิเศษ' },
    { code: 'TLZC', slug: 'lao', name: 'หวยลาว' },
    { code: 'ZCVIP', slug: 'lao-vip', name: 'หวยลาว VIP' },
    { code: 'GSHKA', slug: 'hangseng-morning', name: 'หุ้นฮั่งเส็ง เช้า' },
    { code: 'GSHKP', slug: 'hangseng-afternoon', name: 'หุ้นฮั่งเส็ง บ่าย' },
    { code: 'GSJPA', slug: 'nikkei-morning', name: 'นิเคอิ เช้า' },
    { code: 'GSJPP', slug: 'nikkei-afternoon', name: 'นิเคอิ บ่าย' },
    { code: 'GSKR', slug: 'korea', name: 'หุ้นเกาหลี' },
    { code: 'GSCNA', slug: 'china-morning', name: 'หุ้นจีน เช้า' },
    { code: 'GSCNP', slug: 'china-afternoon', name: 'หุ้นจีน บ่าย' },
    { code: 'GSTW', slug: 'taiwan', name: 'หุ้นไต้หวัน' },
    { code: 'GSSG', slug: 'singapore', name: 'หุ้นสิงคโปร์' },
    { code: 'GSTH', slug: 'thai-stock', name: 'หุ้นไทย' },
    { code: 'GSIN', slug: 'india', name: 'หุ้นอินเดีย' },
    { code: 'GSEG', slug: 'egypt', name: 'หุ้นอียิปต์' },
    { code: 'GSRU', slug: 'russia', name: 'หุ้นรัสเซีย' },
    { code: 'GSDE', slug: 'germany', name: 'หุ้นเยอรมัน' },
    { code: 'GSUK', slug: 'uk', name: 'หุ้นอังกฤษ' },
    { code: 'GSUS', slug: 'dowjones', name: 'หุ้นดาวโจนส์' },
];

const thaiMonths = {
    'มกราคม': '01', 'กุมภาพันธ์': '02', 'มีนาคม': '03',
    'เมษายน': '04', 'พฤษภาคม': '05', 'มิถุนายน': '06',
    'กรกฎาคม': '07', 'สิงหาคม': '08', 'กันยายน': '09',
    'ตุลาคม': '10', 'พฤศจิกายน': '11', 'ธันวาคม': '12'
};

async function scrapeHistory(browser, lotteryCode, slug, maxPages = 5) {
    const url = `https://th.manycai.com/Issue/history?lottername=${lotteryCode}`;
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    await page.setViewport({ width: 1280, height: 800 });

    const allResults = [];

    try {
        console.error(`[${slug}] Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(r => setTimeout(r, 3000));

        for (let pageNum = 1; pageNum <= maxPages; pageNum++) {
            console.error(`[${slug}] Scraping page ${pageNum}...`);

            // Extract rows from current page (including codelist attribute for 2 ตัวล่าง)
            const rows = await page.evaluate(() => {
                const trs = document.querySelectorAll('table tr');
                const data = [];
                trs.forEach(tr => {
                    const tds = tr.querySelectorAll('td');
                    if (tds.length < 3) return;

                    // Get digits from the number circles (first column)
                    const numSpans = tds[0].querySelectorAll('span');
                    let firstPrize = '';
                    numSpans.forEach(s => {
                        const digit = s.textContent.trim();
                        if (/^\d$/.test(digit)) firstPrize += digit;
                    });

                    if (!firstPrize || !/^\d{3,6}$/.test(firstPrize)) return;

                    // Get date text (second column) 
                    const dateText = tds[1].textContent.trim();

                    // Get datetime (third column)
                    const datetime = tds[2]?.textContent.trim() || '';

                    // Extract code1 (2 ตัวล่าง) from codelist attribute on the detail link
                    let twoBottom = '';
                    const detailLink = tr.querySelector('a[codelist]') || tr.querySelector('[codelist]');
                    if (detailLink) {
                        try {
                            const codelist = JSON.parse(detailLink.getAttribute('codelist'));
                            if (codelist.code1) {
                                twoBottom = codelist.code1.replace(/,/g, '');
                            }
                        } catch (e) { }
                    }
                    // Fallback for 4+ digit lotteries: 2 ตัวล่าง = first 2 digits
                    if (!twoBottom && firstPrize.length >= 4) {
                        twoBottom = firstPrize.slice(0, 2);
                    }

                    data.push({ firstPrize, dateText, datetime, twoBottom });
                });
                return data;
            });

            for (const row of rows) {
                let drawDate = null;
                const isoMatch = row.datetime.match(/(\d{4}-\d{2}-\d{2})/);
                if (isoMatch) {
                    drawDate = isoMatch[1];
                }

                if (!drawDate && row.dateText) {
                    const thaiMatch = row.dateText.match(/(\d{1,2})\s+(\S+)\s+(\d{4})/);
                    if (thaiMatch) {
                        const day = thaiMatch[1].padStart(2, '0');
                        const monthName = thaiMatch[2];
                        const year = parseInt(thaiMatch[3]) - 543;
                        for (const [name, num] of Object.entries({
                            'มกราคม': '01', 'กุมภาพันธ์': '02', 'มีนาคม': '03',
                            'เมษายน': '04', 'พฤษภาคม': '05', 'มิถุนายน': '06',
                            'กรกฎาคม': '07', 'สิงหาคม': '08', 'กันยายน': '09',
                            'ตุลาคม': '10', 'พฤศจิกายน': '11', 'ธันวาคม': '12'
                        })) {
                            if (monthName === name) {
                                drawDate = `${year}-${num}-${day}`;
                                break;
                            }
                        }
                    }
                }

                // two_bottom ต้องได้จาก codelist.code1 เท่านั้น (ไม่สามารถ derive จาก first_prize)
                let twoBottom = row.twoBottom || '';

                if (drawDate) {
                    allResults.push({
                        slug,
                        first_prize: row.firstPrize,
                        two_top: row.firstPrize.slice(-2),
                        three_top: row.firstPrize.slice(-3),
                        two_bottom: twoBottom,
                        draw_date: drawDate,
                    });
                }
            }

            console.error(`[${slug}] Page ${pageNum}: found ${rows.length} rows, total so far: ${allResults.length}`);

            // Try to go to next page
            if (pageNum < maxPages) {
                const hasNext = await page.evaluate((nextPage) => {
                    // Try all pagination links
                    const allLinks = document.querySelectorAll('a');
                    for (const link of allLinks) {
                        const text = link.textContent.trim();
                        // Look for next page number or "next" text
                        if (text === String(nextPage) || text === 'หน้าถัดไป' || text === '下一页' || text === '>') {
                            link.click();
                            return true;
                        }
                    }
                    return false;
                }, pageNum + 1);

                if (!hasNext) {
                    console.error(`[${slug}] No next page after page ${pageNum}`);
                    break;
                }

                await new Promise(r => setTimeout(r, 2000));
            }
        }
    } catch (e) {
        console.error(`[${slug}] Error: ${e.message}`);
    } finally {
        await page.close();
    }

    return allResults;
}

// Main
const args = process.argv.slice(2);
const lotteryCode = args[0] || 'ALL';
const maxPages = parseInt(args[1]) || 5;

let browser;
try {
    browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    let allResults = [];

    if (lotteryCode === 'ALL') {
        for (const lottery of ALL_CODES) {
            const results = await scrapeHistory(browser, lottery.code, lottery.slug, maxPages);
            allResults = allResults.concat(results);
            // Rate limit between lotteries
            await new Promise(r => setTimeout(r, 1000));
        }
    } else {
        const lottery = ALL_CODES.find(l => l.code === lotteryCode) || { code: lotteryCode, slug: lotteryCode.toLowerCase() };
        allResults = await scrapeHistory(browser, lottery.code, lottery.slug, maxPages);
    }

    // Output JSON to stdout
    console.log(JSON.stringify({ success: true, count: allResults.length, results: allResults }));

} catch (e) {
    console.error(`Fatal error: ${e.message}`);
    console.log(JSON.stringify({ success: false, error: e.message, results: [] }));
} finally {
    if (browser) await browser.close();
}
