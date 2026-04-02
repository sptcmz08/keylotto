import fs from 'fs/promises';
import path from 'path';
import puppeteer from 'puppeteer';

const [, , inputPath, outputPath] = process.argv;

if (!inputPath || !outputPath) {
  console.error('Usage: node scripts/render_line_result_image.js <input.json> <output.png>');
  process.exit(1);
}

const escapeHtml = (value) =>
  String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

const renderNumberCard = (label, value, tone) => `
  <div class="number-card ${tone}">
    <div class="number-label">${escapeHtml(label)}</div>
    <div class="number-value">${escapeHtml(value || '-')}</div>
  </div>
`;

const main = async () => {
  const raw = await fs.readFile(inputPath, 'utf8');
  const data = JSON.parse(raw);

  await fs.mkdir(path.dirname(outputPath), { recursive: true });

  const html = `
  <!doctype html>
  <html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: "Prompt", "Noto Sans Thai", sans-serif;
        background:
          radial-gradient(circle at top left, rgba(28, 179, 92, 0.22), transparent 36%),
          linear-gradient(180deg, #eef9f0 0%, #f8fbff 100%);
        color: #163424;
      }
      .frame {
        width: 1040px;
        margin: 0 auto;
        padding: 44px;
      }
      .card {
        background: rgba(255,255,255,0.96);
        border: 1px solid rgba(19, 111, 59, 0.16);
        border-radius: 30px;
        box-shadow: 0 24px 80px rgba(22, 52, 36, 0.12);
        overflow: hidden;
      }
      .hero {
        padding: 34px 38px 26px;
        background: linear-gradient(135deg, #118847 0%, #13a455 55%, #2ac56d 100%);
        color: #fff;
      }
      .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: 0.2px;
        opacity: 0.96;
      }
      .site-badge {
        display: inline-block;
        margin-top: 18px;
        padding: 10px 16px;
        border-radius: 999px;
        background: rgba(255,255,255,0.18);
        font-size: 18px;
      }
      .lottery-name {
        margin: 22px 0 8px;
        font-size: 54px;
        line-height: 1.08;
        font-weight: 700;
      }
      .meta {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        font-size: 24px;
        opacity: 0.95;
      }
      .content {
        padding: 34px 38px 40px;
      }
      .summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
      }
      .number-card {
        border-radius: 24px;
        padding: 22px 20px 26px;
        min-height: 182px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }
      .number-card.primary { background: linear-gradient(180deg, #ebfff2 0%, #d7f7e2 100%); }
      .number-card.secondary { background: linear-gradient(180deg, #eef7ff 0%, #dceeff 100%); }
      .number-card.accent { background: linear-gradient(180deg, #fff7e8 0%, #ffe8b9 100%); }
      .number-label {
        font-size: 24px;
        color: #486154;
        font-weight: 600;
      }
      .number-value {
        font-size: 76px;
        line-height: 1;
        font-weight: 700;
        letter-spacing: 2px;
        color: #10291b;
      }
      .footer {
        margin-top: 24px;
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: center;
      }
      .summary-box {
        flex: 1;
        border-radius: 22px;
        padding: 18px 20px;
        background: #f5fbf7;
        border: 1px solid #ddeee3;
      }
      .summary-title {
        font-size: 18px;
        color: #567164;
        margin-bottom: 6px;
      }
      .summary-text {
        font-size: 28px;
        line-height: 1.35;
        font-weight: 600;
        color: #173525;
      }
      .stamp {
        min-width: 250px;
        padding: 18px 20px;
        border-radius: 22px;
        background: linear-gradient(180deg, #163f29 0%, #0f2c1d 100%);
        color: #d9ffe6;
      }
      .stamp .label {
        font-size: 16px;
        opacity: 0.84;
        margin-bottom: 6px;
      }
      .stamp .value {
        font-size: 20px;
        line-height: 1.4;
      }
    </style>
  </head>
  <body>
    <div class="frame">
      <div class="card">
        <div class="hero">
          <div class="eyebrow">ประกาศผลหวย</div>
          <div class="site-badge">${escapeHtml(data.site_name || '')}</div>
          <div class="lottery-name">${escapeHtml(data.lottery_name || 'ผลหวย')}</div>
          <div class="meta">
            <div>${escapeHtml(data.category_name || '')}</div>
            <div>งวดวันที่ ${escapeHtml(data.draw_date_display || data.draw_date || '')}</div>
          </div>
        </div>
        <div class="content">
          <div class="summary">
            ${renderNumberCard('3 ตัวบน', data.three_top, 'primary')}
            ${renderNumberCard('2 ตัวบน', data.two_top, 'secondary')}
            ${renderNumberCard('2 ตัวล่าง', data.two_bot, 'accent')}
          </div>
          <div class="footer">
            <div class="summary-box">
              <div class="summary-title">สรุปผลล่าสุด</div>
              <div class="summary-text">${escapeHtml(data.summary_text || '')}</div>
            </div>
            <div class="stamp">
              <div class="label">สร้างภาพเมื่อ</div>
              <div class="value">${escapeHtml(data.generated_at || '')}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </body>
  </html>`;

  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1040, height: 1200, deviceScaleFactor: 2 });
    await page.setContent(html, { waitUntil: 'networkidle0' });
    await new Promise((resolve) => setTimeout(resolve, 1200));
    await page.screenshot({
      path: outputPath,
      type: 'png',
      fullPage: true,
    });
  } finally {
    await browser.close();
  }
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
