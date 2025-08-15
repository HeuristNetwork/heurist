
'use strict';

/**
 * Deepwiki — Menu-only list-then-download (prefixless output + link rewrite)
 * -------------------------------------------------------------------------
 * Usage:
 *   node crawl_deepwiki7_patched.js            # list pages only (no download)
 *   node crawl_deepwiki7_patched.js --download # list + download
 *
 * What changed:
 *  - Output paths NO LONGER include hostname or /HeuristNetwork/heurist/.
 *  - All internal links under that path are rewritten to relative, prefixless paths.
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const puppeteer = require('puppeteer');
const cheerio = require('cheerio');
const fetch = require('node-fetch'); // v2

// ======================= CONFIG ========================
const START_URL = 'https://deepwiki.com/HeuristNetwork/heurist';
const OUTPUT_DIR = path.resolve('./deepwiki_download');

// Crawl scope
const PATH_PREFIX = '/HeuristNetwork/heurist'; // restrict to this exact path and its subpaths

// Menu selector provided by the user; strict first, then fallbacks
const MENU_SELECTOR = 'ul.flex-1.flex-shrink-0.space-y-1.overflow-y-auto.py-1 a[href]';
const MENU_FALLBACKS = [
  'nav [role="navigation"] a[href^="/HeuristNetwork/heurist"]',
  'aside a[href^="/HeuristNetwork/heurist"]'
];

// Puppeteer flags (safe if running as root)
const PUP_ARGS = ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'];
// =======================================================

const SHOULD_DOWNLOAD = process.argv.includes('--download');

function sleep(ms){ return new Promise(r => setTimeout(r, ms)); }
function hashName(u){ return crypto.createHash('sha1').update(u).digest('hex'); }
function posixRel(from, to){ return path.relative(from, to).split(path.sep).join('/'); }
function stripTrailingSlashes(s){ return s.replace(/\/+$/, ''); }
function escapeRegex(s){ return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

function extFromContentType(ct = ''){
  ct = String(ct || '').toLowerCase();
  if (ct.includes('text/css')) return '.css';
  if (ct.includes('image/jpeg') || ct.includes('image/jpg')) return '.jpg';
  if (ct.includes('image/png')) return '.png';
  if (ct.includes('image/webp')) return '.webp';
  if (ct.includes('image/gif')) return '.gif';
  if (ct.includes('image/svg+xml')) return '.svg';
  if (ct.includes('image/avif')) return '.avif';
  if (ct.includes('font/woff2')) return '.woff2';
  if (ct.includes('font/woff')) return '.woff';
  if (ct.includes('font/ttf') || ct.includes('application/x-font-ttf')) return '.ttf';
  if (ct.includes('application/vnd.ms-fontobject')) return '.eot';
  if (ct.includes('application/font-woff')) return '.woff';
  return '';
}

// ===== Output/Path mapping (DROP host & prefix) =====
function getRootDir(){ return OUTPUT_DIR; }
function getAssetsDir(){ return path.join(getRootDir(), '_assets'); }

// Return the dest directory for a given URL, with /HeuristNetwork/heurist/ stripped
function makePathsFromUrl(u){
  const url = new URL(u);
  url.hash = '';
  const prefix = stripTrailingSlashes(PATH_PREFIX);
  const rawPath = stripTrailingSlashes(url.pathname);
  let rel = rawPath.replace(new RegExp('^' + escapeRegex(prefix) + '(?:/)?'), '');
  rel = stripTrailingSlashes(rel);
  const parts = rel ? rel.split('/').filter(Boolean) : [];
  const dir = path.join(OUTPUT_DIR, ...parts); // NO hostname, NO prefix
  const fileHtml = path.join(dir, 'index.html');
  const fileTxt  = path.join(dir, 'index.txt');
  return { dir, fileHtml, fileTxt };
}

// Strict path filter: only exact prefix or subpath; allow dotted slugs; ignore anchors & obvious filetypes
function shouldVisit(urlStr){
  try {
    const u = new URL(urlStr);
    if (u.hash) return false; // ignore anchors

    if (PATH_PREFIX){
      const up = stripTrailingSlashes(u.pathname);
      const pp = stripTrailingSlashes(PATH_PREFIX);
      if (!(up === pp || up.startsWith(pp + '/'))) return false;
    }

    // Allow dotted slugs; only block obvious file extensions
    const ext = path.extname(u.pathname).toLowerCase();
    const disallow = new Set([
      '.png','.jpg','.jpeg','.gif','.webp','.svg',
      '.css','.js','.mjs','.json',
      '.pdf','.zip','.gz','.tgz','.bz2',
      '.woff','.woff2','.ttf','.eot','.ico',
      '.xml','.csv'
    ]);
    if (ext && disallow.has(ext)) return false;

    return true;
  } catch {
    return false;
  }
}

// Asset pipeline — shared assets folder next to root index.html
function makeAssetPipeline(assetsDir){
  fs.mkdirSync(assetsDir, { recursive: true });

  const urlToFilename = new Map(); // URL -> filename inside assetsDir
  const cssProcessed = new Set();
  const tasks = [];

  const enqueue = (absUrl, preferredExt = '') => {
    if (!absUrl) return;
    try {
      const u = new URL(absUrl);
      if (!/^https?:$/i.test(u.protocol)) return;
    } catch { return; }

    if (urlToFilename.has(absUrl)) return;

    const pathname = (() => { try { return new URL(absUrl).pathname; } catch { return ''; } })();
    let ext = preferredExt || path.extname(pathname);
    if (ext && ext.length > 8) ext = '';

    const nameBase = hashName(absUrl);
    urlToFilename.set(absUrl, `${nameBase}${ext || '.bin'}`);

    tasks.push(async () => {
      try {
        const res = await fetch(absUrl, { redirect: 'follow' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        let finalExt = ext;
        if (!finalExt){
          const ct = res.headers.get('content-type') || '';
          finalExt = extFromContentType(ct) || '.bin';
        }
        const finalName = `${nameBase}${finalExt}`;
        const finalPath = path.join(assetsDir, finalName);
        const buf = await res.buffer();
        fs.writeFileSync(finalPath, buf);
        urlToFilename.set(absUrl, finalName);
      } catch (e){
        console.warn(`  ↳ asset failed: ${absUrl} (${e.message})`);
      }
    });
  };

  async function replaceAsync(str, regex, asyncFn){
    const promises = [];
    str.replace(regex, (match, ...args) => {
      const p = asyncFn(match, ...args);
      promises.push(p);
      return match;
    });
    const data = await Promise.all(promises);
    let i = 0;
    return str.replace(regex, () => data[i++]);
  }

  async function processCss(cssUrl){
    if (!cssUrl) return null;
    try { new URL(cssUrl); } catch { return null; }
    if (cssProcessed.has(cssUrl)) return urlToFilename.get(cssUrl) || null;
    cssProcessed.add(cssUrl);

    const nameBase = hashName(cssUrl);
    const cssLocalName = `${nameBase}.css`;
    const cssLocalPath = path.join(assetsDir, cssLocalName);
    urlToFilename.set(cssUrl, cssLocalName);

    try {
      const res = await fetch(cssUrl, { redirect: 'follow' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      let cssText = await res.text();

      cssText = await replaceAsync(cssText,
        /@import\s+(?:url\(\s*)?['"]?([^'")\s]+)['"]?(?:\s*\))?([^;]*);/gi,
        async (_m, href, media) => {
          try {
            const abs = new URL(href, cssUrl).toString();
            const local = await processCss(abs);
            return local ? `@import url(${local})${media || ''};` : _m;
          } catch { return _m; }
        }
      );

      cssText = await replaceAsync(cssText,
        /url\(\s*(['"]?)([^'")]+)\1\s*\)/gi,
        async (_m, _q, href) => {
          try {
            if (/^data:/i.test(href)) return _m;
            const abs = new URL(href, cssUrl).toString();
            enqueue(abs);
            const file = urlToFilename.get(abs);
            return file ? `url(${file})` : _m;
          } catch { return _m; }
        }
      );

      fs.writeFileSync(cssLocalPath, cssText, 'utf-8');
      return cssLocalName;
    } catch (e){
      console.warn(`  ↳ css failed: ${cssUrl} (${e.message})`);
      return null;
    }
  }

  async function run(concurrency = 8){
    const workers = Array.from({ length: concurrency }, async () => {
      while (tasks.length){
        const t = tasks.shift();
        if (t) await t();
      }
    });
    await Promise.all(workers);
  }

  return { enqueue, processCss, run, urlToFilename };
}

// Expand nav panels
async function expandNav(page){
  const ROUNDS = 8;
  for (let i = 0; i < ROUNDS; i++){
    const clicked = await page.evaluate(() => {
      let clicks = 0;
      const qs = [
        '[aria-expanded="false"]',
        '[data-collapsed="true"]',
        '.menu__link--sublist',
        'summary',
        'button[aria-controls]'
      ];
      const nodes = [];
      qs.forEach(sel => nodes.push(...Array.from(document.querySelectorAll(sel))));
      nodes.forEach(el => { try { el.click(); clicks++; } catch(e){} });
      return clicks;
    });
    if (!clicked) break;
    await sleep(200);
  }
}

// Parse the menu on the root page and return an ordered list of URLs
async function parseMenuLinks(browser){
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Safari/537.36');
  await page.setViewport({ width: 1280, height: 900 });

  // Block heavy resources
  await page.setRequestInterception(true);
  page.on('request', req => {
    const type = req.resourceType();
    if (['image','media','font','stylesheet'].includes(type)) return req.abort();
    req.continue();
  });

  await page.goto(START_URL, { waitUntil: 'networkidle2', timeout: 60_000 });
  await page.waitForSelector('body', { timeout: 30_000 });
  await page.waitForSelector('ul.flex-1.flex-shrink-0.space-y-1.overflow-y-auto.py-1', { timeout: 10000 }).catch(()=>{});
  await expandNav(page);

  // Try the main selector; if empty, try fallbacks
  let links = await page.$$eval(MENU_SELECTOR, as => as.map(a => ({
    href: a.getAttribute('href'),
    text: (a.textContent || '').trim()
  }))).catch(() => []);

  if (!links.length){
    for (const sel of MENU_FALLBACKS){
      try {
        links = await page.$$eval(sel, as => as.map(a => ({
          href: a.getAttribute('href'),
          text: (a.textContent || '').trim()
        })));
        if (links.length){
          console.log(`   ↳ Using fallback selector: ${sel} (${links.length} links)`);
          break;
        }
      } catch {}
    }
  } else {
    console.log(`   ↳ Using menu selector: ${MENU_SELECTOR} (${links.length} links)`);
  }

  await page.close();

  // Normalize, filter, dedupe, keep order
  const seen = new Set();
  const out = [];
  for (const { href, text } of links){
    if (!href) continue;
    if (href.startsWith('#')) continue;
    let abs;
    try { abs = new URL(href, START_URL).toString(); } catch { continue; }
    if (!shouldVisit(abs)) continue;
    if (seen.has(abs)) continue;
    seen.add(abs);
    out.push({ url: abs, text });
  }
  return out;
}

// Rewrite internal <a href> links in the HTML to drop the prefix and be RELATIVE to pageDir
function rewriteInternalLinks($, pageUrl, pageDir){
  const pp = stripTrailingSlashes(PATH_PREFIX);

  $('a[href]').each((_, el) => {
    const href = $(el).attr('href');
    if (!href) return;
    if (href.startsWith('#')) return;

    let abs;
    try { abs = new URL(href, pageUrl); } catch { return; }

    // Only rewrite same-origin internal links under PATH_PREFIX
    const u = abs;
    const up = stripTrailingSlashes(u.pathname);
    if (!(up === pp || up.startsWith(pp + '/'))) return;

    // Map to local dest directory (dropping the prefix)
    const target = makePathsFromUrl(u.toString()).dir;
    let rel = posixRel(pageDir, target);
    if (!rel || rel === '') rel = '.';
    // We save pages as directories with index.html, so link to trailing slash.
    if (!rel.endsWith('/')) rel += '/';
    const hash = u.hash || '';
    $(el).attr('href', rel + (hash ? hash : ''));
  });
}

// Save a single page with assets (using a shared pipeline instance)
async function saveSinglePage(browser, url, pipeline){
  const { dir, fileHtml, fileTxt } = makePathsFromUrl(url);
  fs.mkdirSync(dir, { recursive: true });

  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125 Safari/537.36');
  await page.setViewport({ width: 1280, height: 900 });

  await page.setRequestInterception(true);
  page.on('request', req => {
    const type = req.resourceType();
    if (['image','media','font','stylesheet'].includes(type)) return req.abort();
    req.continue();
  });

  console.log(`⏳ Rendering ${url}`);
  await page.goto(url, { waitUntil: 'networkidle2', timeout: 60_000 });
  await page.waitForSelector('body', { timeout: 30_000 });

  const html = await page.content();
  const $ = cheerio.load(html, { decodeEntities: false });

  // External CSS -> process & rewrite
  const styles = [];
  $('link[rel~="stylesheet"][href]').each((_, el) => {
    const href = $(el).attr('href');
    try { styles.push(new URL(href, url).toString()); } catch {}
  });
  for (const s of styles){
    const localName = await pipeline.processCss(s);
    if (localName){
      const rel = posixRel(dir, path.join(getAssetsDir(), localName));
      $('link[rel~="stylesheet"][href]').each((_, el) => {
        const href = $(el).attr('href'); if (!href) return;
        try {
          const abs = new URL(href, url).toString();
          if (abs === s) $(el).attr('href', rel.startsWith('.') ? rel : './' + rel);
        } catch {}
      });
    }
  }

  // Collect image & inline background assets
  const splitSrcset = (srcset) => String(srcset || '').split(',').map(s=>s.trim()).filter(Boolean).map(item=>{
    const m = item.match(/^(\S+)(?:\s+(.*))?$/);
    return { url: m ? m[1] : item, descriptor: (m && m[2]) ? m[2] : '' };
  });

  $('img').each((_, el) => {
    const src = $(el).attr('src') || $(el).attr('data-src') || $(el).attr('data-original') || $(el).attr('data-lazy') || $(el).attr('data-url');
    const srcset = $(el).attr('srcset') || $(el).attr('data-srcset');
    if (src && !src.startsWith('data:')) pipeline.enqueue(new URL(src, url).toString());
    if (srcset){
      for (const { url: u } of splitSrcset(srcset)){
        if (u && !u.startsWith('data:')) pipeline.enqueue(new URL(u, url).toString());
      }
    }
  });
  $('source[srcset], source[data-srcset]').each((_, el) => {
    const srcset = $(el).attr('srcset') || $(el).attr('data-srcset');
    if (!srcset) return;
    for (const { url: u } of splitSrcset(srcset)){
      if (u && !u.startsWith('data:')) pipeline.enqueue(new URL(u, url).toString());
    }
  });
  $('[style*="background"]').each((_, el) => {
    const style = $(el).attr('style') || '';
    const m = style.match(/background(?:-image)?:\s*url\((['"]?)(.*?)\1\)/i);
    if (m && m[2] && !m[2].startsWith('data:')) pipeline.enqueue(new URL(m[2], url).toString());
  });

  // Inline <style> blocks: rewrite url()/@import to shared assets
  async function replaceAsync(str, regex, asyncFn){
    const promises = [];
    str.replace(regex, (match, ...args) => { const p = asyncFn(match, ...args); promises.push(p); return match; });
    const data = await Promise.all(promises);
    let i = 0; return str.replace(regex, () => data[i++]);
  }
  async function rewriteInline(cssText, baseUrl){
    let txt = cssText;
    txt = await replaceAsync(txt,
      /@import\s+(?:url\(\s*)?['"]?([^'")\s]+)['"]?(?:\s*\))?([^;]*);/gi,
      async (_m, href, media) => {
        try {
          const abs = new URL(href, baseUrl).toString();
          const localName = await pipeline.processCss(abs);
          if (!localName) return _m;
          const rel = posixRel(dir, path.join(getAssetsDir(), localName));
          return `@import url(${rel.startsWith('.') ? rel : './' + rel})${media || ''};`;
        } catch { return _m; }
      }
    );
    txt = await replaceAsync(txt,
      /url\(\s*(['"]?)([^'")]+)\1\s*\)/gi,
      async (_m, _q, href) => {
        try {
          if (/^data:/i.test(href)) return _m;
          const abs = new URL(href, baseUrl).toString();
          pipeline.enqueue(abs);
          const file = pipeline.urlToFilename.get(abs);
          if (!file) return _m;
          const rel = posixRel(dir, path.join(getAssetsDir(), file));
          return `url(${rel.startsWith('.') ? rel : './' + rel})`;
        } catch { return _m; }
      }
    );
    return txt;
  }
  const inlineStyles = [];
  $('style').each((_, el) => inlineStyles.push(el));
  for (const el of inlineStyles){
    const cssText = $(el).html() || '';
    const rewritten = await rewriteInline(cssText, url);
    $(el).text(rewritten);
  }

  // Download all enqueued assets now
  await pipeline.run(8);

  // Final HTML rewrites for <img> and inline background to local assets
  $('img').each((_, el) => {
    const src = $(el).attr('src') || $(el).attr('data-src') || $(el).attr('data-original') || $(el).attr('data-lazy') || $(el).attr('data-url');
    if (src && !src.startsWith('data:')){
      try {
        const abs = new URL(src, url).toString();
        const file = pipeline.urlToFilename.get(abs);
        if (file){
          const rel = posixRel(dir, path.join(getAssetsDir(), file));
          $(el).attr('src', rel.startsWith('.') ? rel : './' + rel);
        }
      } catch {}
    }
    const srcset = $(el).attr('srcset') || $(el).attr('data-srcset');
    if (srcset){
      const items = String(srcset).split(',').map(s=>s.trim()).filter(Boolean).map(item => {
        const m = item.match(/^(\S+)(?:\s+(.*))?$/);
        const urlPart = m ? m[1] : item;
        const descriptor = (m && m[2]) ? m[2] : '';
        try {
          const abs = new URL(urlPart, url).toString();
          const file = pipeline.urlToFilename.get(abs);
          if (!file) return null;
          const rel = posixRel(dir, path.join(getAssetsDir(), file));
          return `${(rel.startsWith('.') ? rel : './' + rel)}${descriptor ? ' ' + descriptor : ''}`;
        } catch { return null; }
      }).filter(Boolean);
      if (items.length) $(el).attr('srcset', items.join(', '));
    }
  });

  $('[style*="background"]').each((_, el) => {
    const style = $(el).attr('style') || '';
    const newStyle = style.replace(/background(?:-image)?:\s*url\((['"]?)(.*?)\1\)/ig, (m, q, href) => {
      try {
        const abs = new URL(href, url).toString();
        const file = pipeline.urlToFilename.get(abs);
        if (!file) return m;
        const rel = posixRel(dir, path.join(getAssetsDir(), file));
        return `background-image:url(${rel.startsWith('.') ? rel : './' + rel})`;
      } catch { return m; }
    });
    $(el).attr('style', newStyle);
  });

  // NEW: rewrite internal navigation links to drop PATH_PREFIX and be relative
  rewriteInternalLinks($, url, dir);

  const finalHtml = $.html();
  fs.writeFileSync(fileHtml, finalHtml, 'utf-8');

  // Save text
  const text = cheerio.load(finalHtml)('body').text();
  fs.writeFileSync(fileTxt, text, 'utf-8');

  await page.close();
  console.log(`✅ Saved ${fileHtml}`);
}

// MAIN
(async () => {
  const browser = await puppeteer.launch({ headless: true, args: PUP_ARGS });

  try {
    // 1) Parse the menu once
    console.log('⏳ Parsing menu on root page...');
    const items = await parseMenuLinks(browser);

    if (!items.length){
      console.log('⚠️  No menu links found under the provided selector(s).');
      return;
    }

    // 2) Print the list (prefixless view)
    console.log(`\nPages to download (${items.length}):`);
    items.forEach((it, i) => {
      const p = stripTrailingSlashes(new URL(it.url).pathname);
      const pp = stripTrailingSlashes(PATH_PREFIX);
      const nice = stripTrailingSlashes(p.replace(new RegExp('^' + escapeRegex(pp) + '(?:/)?'), ''));
      console.log(`${String(i+1).padStart(3, ' ')}. /${nice || ''}${it.text ? ' — ' + it.text : ''}`);
    });

    if (!SHOULD_DOWNLOAD){
      console.log('\nDRY RUN: pass --download to actually fetch pages and assets.');
      return;
    }

    // 3) Prepare output dirs and single shared pipeline
    fs.mkdirSync(getRootDir(), { recursive: true });
    fs.mkdirSync(getAssetsDir(), { recursive: true });
    const pipeline = makeAssetPipeline(getAssetsDir());

    // Also include the START_URL itself first
    const allUrls = [START_URL, ...items.map(it => it.url)];

    // 4) Download pages sequentially (gentle)
    for (const url of allUrls){
      try {
        await saveSinglePage(browser, url, pipeline);
      } catch (e){
        console.error(`❌ Failed ${url}: ${e.message}`);
      }
      await sleep(200);
    }
  } finally {
    await browser.close();
  }
})();
