import { chromium } from 'playwright';

const BASE = 'http://127.0.0.1:8000';
const results = { console: [], failedRequests: [], httpErrors: [], actions: [] };

const browser = await chromium.launch();
const page = await browser.newPage();

page.on('console', msg => {
  try {
    const l = msg.location ? msg.location() : {};
    results.console.push({ type: msg.type(), text: msg.text(), url: l.url, line: l.lineNumber });
  } catch (e) {
    results.console.push({ type: msg.type(), text: msg.text() });
  }
});

page.on('requestfailed', req => {
  const f = req.failure ? (req.failure() || {}) : {};
  results.failedRequests.push({ url: req.url(), method: req.method(), errorText: f.errorText || (f && f.text) || null });
});

page.on('response', resp => {
  try {
    if (resp.status() >= 400) {
      results.httpErrors.push({ url: resp.url(), status: resp.status(), statusText: resp.statusText() });
    }
  } catch (e) {}
});

async function nav(url) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => {
    results.actions.push('nav err to ' + url + ': ' + e.message);
  });
  await page.waitForTimeout(500);
  results.actions.push('loaded ' + url + ' -> ' + page.url());
}

// 1) LOGIN (direct POST via form, then go straight to suppliers without relying on dashboard redirect)
try {
  await nav('/login');
  await page.fill('input[name="email"]', 'pharmacy@medfind.com');
  await page.fill('input[name="password"]', 'password');
  // Submit without waiting for redirect chain; just click and wait briefly
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(() => {}),
    page.click('button[type="submit"]')
  ]);
  await page.waitForTimeout(800);
  results.actions.push('after login submit -> ' + page.url());
} catch (e) {
  results.loginError = String(e);
}

// 2) SUPPLIERS INDEX (direct navigation, bypass dashboard)
try {
  await page.goto(BASE + '/pharmacy/suppliers', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => {
    results.actions.push('index nav err: ' + e.message);
  });
  await page.waitForTimeout(800);
  results.actions.push('suppliers index -> ' + page.url());
  if (page.url().includes('suppliers')) {
    results.initialSuppliers = await page.$$eval('table tbody tr', r => r.length).catch(() => 0);
  }
} catch (e) {
  results.indexError = String(e);
}

// 3) CREATE
const unique = 'TestSupplier_' + Date.now();
try {
  await page.goto(BASE + '/pharmacy/suppliers/create', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => {
    results.actions.push('create nav err: ' + e.message);
  });
  await page.waitForTimeout(800);
  results.actions.push('create page -> ' + page.url());
  await page.fill('input[name="name"]', unique);
  await page.fill('input[name="contact_person"]', 'QC Tester');
  await page.fill('input[name="phone"]', '09171234567');
  await page.fill('input[name="email"]', 'qc' + Date.now() + '@test.com');
  await page.fill('textarea[name="address"]', '123 Test Ave');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => results.actions.push('create nav after submit err: ' + e)),
    page.click('button[type="submit"]')
  ]);
  await page.waitForTimeout(800);
  results.actions.push('after create submit -> ' + page.url());
} catch (e) {
  results.createError = String(e);
}

// locate the created supplier row
const row = await page.$$eval('table tbody tr', (rs, name) => {
  const r = rs.find(x => x.textContent.includes(name));
  if (!r) return null;
  const e = r.querySelector('a[href*="edit"]');
  const d = r.querySelector('form[action]');
  return { editHref: e ? e.href : null, delAction: d ? d.action : null, text: r.textContent.trim().slice(0, 120) };
}, unique).catch(() => null);
results.createdRow = row;

// 4) EDIT
if (row && row.editHref) {
  try {
    await page.goto(row.editHref, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => results.actions.push('edit nav err: ' + e));
    await page.waitForTimeout(800);
    results.actions.push('edit page -> ' + page.url());
    await page.fill('input[name="name"]', unique + '_EDITED');
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => results.actions.push('edit save nav err: ' + e)),
      page.click('button[type="submit"]')
    ]);
    await page.waitForTimeout(800);
    results.actions.push('after edit submit -> ' + page.url());
  } catch (e) {
    results.editError = String(e);
  }
} else {
  results.editError = 'No edit link found (route likely missing)';
}

// 5) DELETE
if (row && row.delAction) {
  try {
    page.once('dialog', d => d.accept());
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => results.actions.push('delete nav err: ' + e)),
      page.evaluate(a => { const f = document.querySelector('form[action="' + a + '"]'); if (f) f.submit(); }, row.delAction)
    ]);
    await page.waitForTimeout(800);
    results.actions.push('after delete submit -> ' + page.url());
  } catch (e) {
    results.deleteError = String(e);
  }
} else {
  results.deleteError = 'No delete form found (route likely missing)';
}

// 6) VERIFY deleted (no longer present on index)
try {
  await page.goto(BASE + '/pharmacy/suppliers', { waitUntil: 'domcontentloaded', timeout: 60000 }).catch(e => results.actions.push('verify nav err: ' + e));
  await page.waitForTimeout(800);
  results.remainingMatch = await page.$$eval('table tbody tr', (rs, name) => rs.some(x => x.textContent.includes(name)), unique).catch(() => null);
  results.finalUrl = page.url();
} catch (e) {}

await browser.close();

console.log('=== SUPPLIER CRUD TEST RESULTS ===');
console.log(JSON.stringify(results, null, 2));
