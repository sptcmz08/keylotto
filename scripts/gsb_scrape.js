import puppeteer from 'puppeteer';

/**
 * GSB Lottery Scraper (สลากออมสิน)
 * 
 * Usage:
 *   node scripts/gsb_scrape.js [type] [ddMMyyyy]
 *   type: "1year" or "2year" (default: both)
 *   date: draw date in ddMMyyyy format (default: auto-calculate)
 * 
 * Examples:
 *   node scripts/gsb_scrape.js              # Scrape both types, latest date
 *   node scripts/gsb_scrape.js 1year        # Scrape 1-year only
 *   node scripts/gsb_scrape.js 2year 01022026  # Scrape 2-year for 01 Feb 2026
 */

const GSB_TYPES = {
    '1year': {
        slug: 'gsb-1',
        urlPath: 'salak-1year-100',
        name: 'ออมสิน 1 ปี',
        drawDay: 16,  // ออกวันที่ 16 ทุกเดือน
    },
    '2year': {
        slug: 'gsb-2',
        urlPath: 'salak-2year',
        name: 'ออมสิน 2 ปี',
        drawDay: 1,   // ออกวันที่ 1 ทุกเดือน
    },
};

function getLatestDrawDate(drawDay) {
    const now = new Date();
    // Thai timezone offset
    const bangkokOffset = 7 * 60;
    const utcOffset = now.getTimezoneOffset();
    const bangkokTime = new Date(now.getTime() + (bangkokOffset + utcOffset) * 60000);

    let year = bangkokTime.getFullYear();
    let month = bangkokTime.getMonth(); // 0-indexed
    let day = bangkokTime.getDate();

    // If current day < drawDay, use previous month
    if (day < drawDay) {
        month--;
        if (month < 0) {
            month = 11;
            year--;
        }
    }

    const dd = String(drawDay).padStart(2, '0');
    const mm = String(month + 1).padStart(2, '0');
    const yyyy = String(year);

    return { ddMMyyyy: `${dd}${mm}${yyyy}`, isoDate: `${yyyy}-${mm}-${dd}` };
}

async function scrapeGSB(type, dateStr) {
    const config = GSB_TYPES[type];
    if (!config) throw new Error(`Unknown type: ${type}`);

    // Calculate date
    let ddMMyyyy, isoDate;
    if (dateStr) {
        ddMMyyyy = dateStr;
        const dd = dateStr.substring(0, 2);
        const mm = dateStr.substring(2, 4);
        const yyyy = dateStr.substring(4, 8);
        isoDate = `${yyyy}-${mm}-${dd}`;
    } else {
        const d = getLatestDrawDate(config.drawDay);
        ddMMyyyy = d.ddMMyyyy;
        isoDate = d.isoDate;
    }

    const url = `https://psc.gsb.or.th/resultsalak/${config.urlPath}/${ddMMyyyy}`;

    const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    try {
        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        console.error(`📡 Fetching ${config.name}: ${url}`);
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
        await new Promise(r => setTimeout(r, 5000));

        const result = await page.evaluate(() => {
            const text = document.body.innerText;

            // Extract prize 1 number: "อันดับที่ 1" section → "งวดที่ XXX Y NNNNNNN"
            const prize1Match = text.match(/อันดับที่\s*1[\s\S]*?งวดที่\s*\d+\s*\S\s*(\d{6,7})/);
            const firstPrize = prize1Match ? prize1Match[1] : null;

            // Extract prize 2 number: "อันดับที่ 2" section → "งวดที่ XXX Y NNNNNNN"
            const prize2Match = text.match(/อันดับที่\s*2[\s\S]*?งวดที่\s*\d+\s*\S\s*(\d{6,7})/);
            const secondPrize = prize2Match ? prize2Match[1] : null;

            // Extract draw date from page
            const dateMatch = text.match(/วันที่\s*:\s*(\d{1,2})\s+(\S+)\s+(\d{4})/);

            return {
                firstPrize,
                secondPrize,
                dateText: dateMatch ? `${dateMatch[1]} ${dateMatch[2]} ${dateMatch[3]}` : null,
                pageText: text.substring(0, 500), // for debugging
            };
        });

        if (!result.firstPrize) {
            console.error(`❌ ไม่พบรางวัลที่ 1 สำหรับ ${config.name}`);
            return null;
        }

        const threeTop = result.firstPrize.slice(-3);
        const twoTop = result.firstPrize.slice(-2);
        const twoBottom = result.secondPrize ? result.secondPrize.slice(-2) : '';

        const output = {
            lottery_name: config.name,
            slug: config.slug,
            draw_date: isoDate,
            date_thai: result.dateText,
            first_prize: result.firstPrize,
            second_prize: result.secondPrize || '',
            three_top: threeTop,
            two_top: twoTop,
            two_bottom: twoBottom,
        };

        console.error(`✅ ${config.name}: รางวัลที่1=${result.firstPrize} (3บน=${threeTop}, 2บน=${twoTop}), รางวัลที่2=${result.secondPrize} (2ล่าง=${twoBottom})`);

        return output;

    } finally {
        await browser.close();
    }
}

// Main
(async () => {
    const args = process.argv.slice(2);
    const requestedType = args[0]; // "1year", "2year", or undefined (both)
    const dateArg = args[1]; // ddMMyyyy or undefined

    const typesToScrape = requestedType ? [requestedType] : Object.keys(GSB_TYPES);
    const results = [];

    for (const type of typesToScrape) {
        try {
            const result = await scrapeGSB(type, dateArg);
            if (result) results.push(result);
        } catch (e) {
            console.error(`❌ Error scraping ${type}: ${e.message}`);
        }
    }

    // Output JSON to stdout
    console.log(JSON.stringify({
        success: true,
        source: 'gsb',
        scrapedAt: new Date().toISOString(),
        count: results.length,
        results
    }, null, 2));
})();
