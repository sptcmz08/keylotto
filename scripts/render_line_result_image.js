import fs from 'fs';
import fsp from 'fs/promises';
import path from 'path';
import puppeteer from 'puppeteer';
import { fileURLToPath } from 'url';

const [, , inputPath, outputPath] = process.argv;
const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const rootDir = path.resolve(scriptDir, '..');

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

const renderNumberCard = (label, value, tone, accent) => `
  <section class="number-card ${tone}">
    <div class="number-card__label">${escapeHtml(label)}</div>
    <div class="number-card__value">${escapeHtml(value || '-')}</div>
    <div class="number-card__accent">${escapeHtml(accent)}</div>
  </section>
`;

const findChromeExecutable = () => {
  const cacheDir = path.join(rootDir, '.cache', 'puppeteer');
  const directCandidates = [
    path.join(cacheDir, 'chrome', 'linux-146.0.7680.153', 'chrome-linux64', 'chrome'),
    path.join(cacheDir, 'chrome', 'win64-146.0.7680.153', 'chrome-win64', 'chrome.exe'),
    path.join(cacheDir, 'chrome', 'mac-146.0.7680.153', 'chrome-mac-x64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
    path.join(cacheDir, 'chrome', 'mac_arm-146.0.7680.153', 'chrome-mac-arm64', 'Google Chrome for Testing.app', 'Contents', 'MacOS', 'Google Chrome for Testing'),
  ];

  for (const candidate of directCandidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  const chromeRoot = path.join(cacheDir, 'chrome');
  if (!fs.existsSync(chromeRoot)) {
    return null;
  }

  const stack = [chromeRoot];
  const executableNames = new Set(['chrome', 'chrome.exe']);

  while (stack.length > 0) {
    const current = stack.pop();
    const entries = fs.readdirSync(current, { withFileTypes: true });

    for (const entry of entries) {
      const fullPath = path.join(current, entry.name);
      if (entry.isDirectory()) {
        stack.push(fullPath);
        continue;
      }

      if (executableNames.has(entry.name)) {
        return fullPath;
      }
    }
  }

  return null;
};

const ensureWritableBrowserDirs = async () => {
  const baseCacheDir = path.join(rootDir, '.cache');
  const cacheDir = path.join(baseCacheDir, 'puppeteer');
  const configDir = path.join(baseCacheDir, 'xdg-config');
  const userDataDir = path.join(baseCacheDir, 'puppeteer-profile');

  await fsp.mkdir(cacheDir, { recursive: true });
  await fsp.mkdir(configDir, { recursive: true });
  await fsp.mkdir(userDataDir, { recursive: true });

  process.env.PUPPETEER_CACHE_DIR = cacheDir;
  process.env.XDG_CACHE_HOME = baseCacheDir;
  process.env.XDG_CONFIG_HOME = configDir;
  if (!process.env.HOME) {
    process.env.HOME = rootDir;
  }

  return { userDataDir };
};

const findThaiFontPath = () => {
  const candidates = [
    path.join(rootDir, 'line', 'fonts', 'NotoSansThai-Regular.ttf'),
    path.join(rootDir, 'line', 'fonts', 'Prompt-Regular.ttf'),
    '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/opentype/noto/NotoSansThai-Regular.ttf',
    '/usr/share/fonts/truetype/tlwg/Garuda.ttf',
    '/usr/share/fonts/truetype/tlwg/Kinnari.ttf',
    '/usr/share/fonts/truetype/tlwg/Loma.ttf',
    '/usr/share/fonts/truetype/tlwg/Norasi.ttf',
    '/usr/share/fonts/truetype/tlwg/Purisa.ttf',
    '/usr/share/fonts/truetype/tlwg/Sawasdee.ttf',
    '/usr/share/fonts/truetype/tlwg/Umpush.ttf',
    '/usr/share/fonts/truetype/tlwg/Waree.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    'C:/Windows/Fonts/tahoma.ttf',
    'C:/Windows/Fonts/arial.ttf',
  ];

  for (const candidate of candidates) {
    if (fs.existsSync(candidate)) {
      return candidate;
    }
  }

  const scanDirs = [
    path.join(rootDir, 'line', 'fonts'),
    '/usr/share/fonts',
    '/usr/local/share/fonts',
    'C:/Windows/Fonts',
  ];

  const fontNamePattern = /(thai|sarabun|prompt|kanit|garuda|kinnari|loma|norasi|purisa|sawasdee|waree|umpush|tlwg|noto)/i;

  for (const scanDir of scanDirs) {
    if (!fs.existsSync(scanDir)) {
      continue;
    }

    const stack = [scanDir];
    while (stack.length > 0) {
      const current = stack.pop();
      const entries = fs.readdirSync(current, { withFileTypes: true });

      for (const entry of entries) {
        const fullPath = path.join(current, entry.name);
        if (entry.isDirectory()) {
          stack.push(fullPath);
          continue;
        }

        const lowerName = entry.name.toLowerCase();
        if ((lowerName.endsWith('.ttf') || lowerName.endsWith('.otf')) && fontNamePattern.test(entry.name)) {
          return fullPath;
        }
      }
    }
  }

  return null;
};

const buildEmbeddedFontFace = () => {
  const fontPath = findThaiFontPath();
  if (!fontPath) {
    return '';
  }

  const extension = path.extname(fontPath).toLowerCase();
  const mimeType = extension === '.otf' ? 'font/otf' : 'font/ttf';
  const format = extension === '.otf' ? 'opentype' : 'truetype';
  const fontData = fs.readFileSync(fontPath).toString('base64');

  return `
    @font-face {
      font-family: "LineThai";
      src: url("data:${mimeType};base64,${fontData}") format("${format}");
      font-weight: 400 800;
      font-style: normal;
      font-display: block;
    }
  `;
};

const main = async () => {
  const raw = await fsp.readFile(inputPath, 'utf8');
  const data = JSON.parse(raw);

  await fsp.mkdir(path.dirname(outputPath), { recursive: true });
  const { userDataDir } = await ensureWritableBrowserDirs();
  const embeddedFontFace = buildEmbeddedFontFace();

  const html = `
  <!doctype html>
  <html lang="th">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
      ${embeddedFontFace}
      * { box-sizing: border-box; }
      :root {
        --bg-top: #f7fcf8;
        --bg-bottom: #eff7ff;
        --ink: #163224;
        --muted: #5a7466;
        --card: rgba(255, 255, 255, 0.97);
        --border: rgba(19, 92, 61, 0.12);
        --green-1: #0e6f46;
        --green-2: #16955d;
        --green-3: #1bc270;
        --gold: #ffc857;
        --blue: #2e72ff;
      }
      body {
        margin: 0;
        font-family: "LineThai", "Noto Sans Thai", "Tahoma", sans-serif;
        color: var(--ink);
        background:
          radial-gradient(circle at left top, rgba(255, 203, 89, 0.16), transparent 25%),
          radial-gradient(circle at right bottom, rgba(17, 149, 93, 0.14), transparent 28%),
          linear-gradient(180deg, var(--bg-top) 0%, var(--bg-bottom) 100%);
      }
      .frame {
        width: 1080px;
        margin: 0 auto;
        padding: 42px;
      }
      .card {
        position: relative;
        border-radius: 36px;
        overflow: hidden;
        background: var(--card);
        border: 1px solid var(--border);
        box-shadow: 0 28px 100px rgba(20, 50, 36, 0.12);
      }
      .hero {
        position: relative;
        padding: 34px 40px 32px;
        background:
          radial-gradient(circle at 88% 12%, rgba(255,255,255,0.20), transparent 18%),
          linear-gradient(135deg, var(--green-1) 0%, var(--green-2) 48%, var(--green-3) 100%);
        color: #fff;
      }
      .hero::after {
        content: "";
        position: absolute;
        right: -22px;
        bottom: -48px;
        width: 250px;
        height: 250px;
        border-radius: 999px;
        background: rgba(255,255,255,0.08);
      }
      .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-size: 21px;
        font-weight: 700;
        letter-spacing: 0.2px;
      }
      .eyebrow::before {
        content: "";
        width: 14px;
        height: 14px;
        border-radius: 999px;
        background: var(--gold);
        box-shadow: 0 0 0 6px rgba(255, 200, 87, 0.16);
      }
      .hero-meta {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: end;
        flex-wrap: wrap;
        margin-top: 22px;
      }
      .site-tag {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        border-radius: 999px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.16);
        font-size: 16px;
      }
      .lottery-name {
        margin: 18px 0 10px;
        font-size: 58px;
        line-height: 1.04;
        font-weight: 800;
      }
      .category-name {
        font-size: 22px;
        opacity: 0.96;
      }
      .draw-pill {
        padding: 14px 18px;
        border-radius: 18px;
        background: rgba(0, 0, 0, 0.14);
        backdrop-filter: blur(8px);
        min-width: 240px;
      }
      .draw-pill__label {
        font-size: 14px;
        opacity: 0.82;
        margin-bottom: 6px;
      }
      .draw-pill__value {
        font-size: 24px;
        font-weight: 700;
      }
      .content {
        padding: 28px 40px 40px;
      }
      .highlight {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        padding: 18px 20px;
        border-radius: 24px;
        background: linear-gradient(90deg, #f5fdf8 0%, #ebfff2 100%);
        border: 1px solid #d8f0de;
      }
      .highlight__title {
        font-size: 22px;
        font-weight: 700;
        color: #165437;
      }
      .highlight__sub {
        margin-top: 5px;
        font-size: 16px;
        color: var(--muted);
      }
      .highlight__stamp {
        padding: 12px 18px;
        border-radius: 999px;
        background: #153f2a;
        color: #d9ffe6;
        font-size: 16px;
        font-weight: 700;
        white-space: nowrap;
      }
      .numbers {
        margin-top: 22px;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
      }
      .number-card {
        position: relative;
        min-height: 200px;
        border-radius: 28px;
        padding: 22px 22px 26px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
      }
      .number-card::after {
        content: "";
        position: absolute;
        right: -20px;
        bottom: -26px;
        width: 110px;
        height: 110px;
        border-radius: 999px;
        background: rgba(255,255,255,0.28);
      }
      .number-card.primary {
        background: linear-gradient(180deg, #ecfdf2 0%, #d9f6e4 100%);
      }
      .number-card.secondary {
        background: linear-gradient(180deg, #eef5ff 0%, #dce9ff 100%);
      }
      .number-card.accent {
        background: linear-gradient(180deg, #fff8ea 0%, #ffe7b6 100%);
      }
      .number-card__label,
      .number-card__value,
      .number-card__accent {
        position: relative;
        z-index: 1;
      }
      .number-card__label {
        font-size: 22px;
        color: #4a6358;
        font-weight: 700;
      }
      .number-card__value {
        font-size: 88px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: 1px;
        color: #102f1f;
      }
      .number-card__accent {
        font-size: 15px;
        color: rgba(16, 47, 31, 0.68);
      }
      .footer {
        margin-top: 22px;
        display: grid;
        grid-template-columns: 1.7fr 0.9fr;
        gap: 18px;
      }
      .detail-box,
      .time-box {
        border-radius: 24px;
        overflow: hidden;
      }
      .detail-box {
        background: linear-gradient(180deg, #f7fbf8 0%, #eef6f1 100%);
        border: 1px solid #ddeee2;
        padding: 22px 22px 24px;
      }
      .detail-box__label {
        font-size: 16px;
        color: var(--muted);
        margin-bottom: 10px;
      }
      .detail-box__value {
        font-size: 30px;
        line-height: 1.44;
        font-weight: 700;
        color: #173227;
        word-break: break-word;
      }
      .time-box {
        background:
          linear-gradient(180deg, #163f29 0%, #0f2c1d 100%);
        color: #d8ffe5;
        padding: 22px;
      }
      .time-box__label {
        font-size: 15px;
        opacity: 0.82;
        margin-bottom: 8px;
      }
      .time-box__value {
        font-size: 22px;
        line-height: 1.45;
        font-weight: 700;
        word-break: break-word;
      }
    </style>
  </head>
  <body>
    <div class="frame">
      <div class="card">
        <section class="hero">
          <div class="eyebrow">ประกาศผลหวย</div>
          <div class="hero-meta">
            <div>
              <div class="site-tag">${escapeHtml(data.site_name || '')}</div>
              <div class="lottery-name">${escapeHtml(data.lottery_name || 'ผลหวย')}</div>
              <div class="category-name">${escapeHtml(data.category_name || '')}</div>
            </div>
            <div class="draw-pill">
              <div class="draw-pill__label">งวดประจำวันที่</div>
              <div class="draw-pill__value">${escapeHtml(data.draw_date_display || data.draw_date || '')}</div>
            </div>
          </div>
        </section>

        <section class="content">
          <div class="highlight">
            <div>
              <div class="highlight__title">ผลออกรอบล่าสุด</div>
              <div class="highlight__sub">ภาพนี้ถูกสร้างจากข้อมูลผลหวยในระบบเพื่อส่งเข้า LINE กลุ่ม</div>
            </div>
            <div class="highlight__stamp">อัปเดตอัตโนมัติ</div>
          </div>

          <div class="numbers">
            ${renderNumberCard('3 ตัวบน', data.three_top, 'primary', 'Three Top')}
            ${renderNumberCard('2 ตัวบน', data.two_top, 'secondary', 'Two Top')}
            ${renderNumberCard('2 ตัวล่าง', data.two_bot, 'accent', 'Two Bottom')}
          </div>

          <div class="footer">
            <div class="detail-box">
              <div class="detail-box__label">รายละเอียดผลหวย</div>
              <div class="detail-box__value">${escapeHtml(data.summary_text || '')}</div>
            </div>
            <div class="time-box">
              <div class="time-box__label">สร้างภาพเมื่อ</div>
              <div class="time-box__value">${escapeHtml(data.generated_at || '')}</div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </body>
  </html>`;

  const launchOptions = {
    headless: 'new',
    userDataDir,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-crash-reporter',
      '--disable-crashpad',
      '--disable-crashpad-for-testing',
      '--no-first-run',
      '--no-default-browser-check',
    ],
  };

  const executablePath = findChromeExecutable();
  if (executablePath) {
    launchOptions.executablePath = executablePath;
  }

  const browser = await puppeteer.launch(launchOptions);

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1080, height: 1320, deviceScaleFactor: 2 });
    await page.setContent(html, { waitUntil: 'networkidle0' });
    await page.evaluate(async () => {
      if (document.fonts?.ready) {
        await document.fonts.ready;
      }
    });
    await new Promise((resolve) => setTimeout(resolve, 600));
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
