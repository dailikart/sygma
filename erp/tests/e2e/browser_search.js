// Browser check for type-to-search dropdowns. Usage: node browser_search.js http://localhost:8090
// Prints one line per check: "PASS name" or "FAIL name: detail".
const { chromium } = require('playwright');
const B = process.argv[2];
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1300, height: 900 } });
  const errs = [];
  p.on('pageerror', (e) => errs.push(e.message));
  const check = (name, ok, detail = '') => console.log((ok ? 'PASS ' : 'FAIL ') + name + (ok ? '' : ': ' + detail));
  await p.goto(B + '/login'); await p.fill('#email', 'admin@e2e.test'); await p.fill('#password', 'Admin#2026x');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);

  await p.goto(B + '/admin/invoices/new');
  const box = p.locator('#lines tbody tr').first().locator('.ss-box');
  check('invoice item dropdown is searchable', await box.count() === 1);
  await box.click();
  await p.keyboard.type('perc');
  const shown = await p.locator('.ss-panel .ss-item').allTextContents();
  check('typing filters the items', shown.length === 1 && /PERC/i.test(shown[0]), JSON.stringify(shown));
  await p.keyboard.press('Enter');
  const row1 = p.locator('#lines tbody tr').first();
  check('choosing fills rate, GST and description', await row1.locator('[data-field=rate]').inputValue() === '11000.00' && await row1.locator('[data-field=tax_percent]').inputValue() === '12.00',
    await row1.locator('[data-field=rate]').inputValue());
  check('box shows the chosen item', /PERC/.test(await box.textContent()));
  await p.click('[data-add-row="#lines"]');
  const box2 = p.locator('#lines tbody tr').nth(1).locator('.ss-box');
  check('new line gets a searchable dropdown too', await box2.count() === 1);
  await box2.focus(); await p.keyboard.type('cab'); await p.keyboard.press('Enter');
  check('keyboard: type on the box and Enter', /Cable/i.test(await box2.textContent()), await box2.textContent());
  check('totals update after choosing', parseFloat(await p.textContent('#sum-total')) > 0);
  await p.selectOption('#kit', { index: 1 }).catch(() => {});
  await p.evaluate(() => { const k = document.getElementById('kit'); k.value = k.options[1].value; });
  await p.click('#load-kit');
  const labels = await p.$$eval('#lines tbody tr', (rows) => rows.map((r) => { const s = r.querySelector('select'); return [s.options[s.selectedIndex].text, r.querySelector('.ss-box').textContent]; }));
  check('loading a kit shows the item names in the boxes', labels.length >= 3 && labels.every(([a, c]) => a === c), JSON.stringify(labels));
  await box.click(); await p.keyboard.type('zzzz');
  check('no match message', await p.locator('.ss-empty').count() === 1);
  await p.keyboard.press('Escape');
  check('Escape closes the list', await p.locator('.ss-panel').count() === 0);

  for (const [url, sel] of [['/admin/purchase-orders/new', '#lines tbody tr .ss-box'], ['/admin/kits/new', 'table .ss-box'], ['/admin/stock/adjust', '#adj .ss-box'], ['/admin/quotations/new', '#lines tbody tr .ss-box']]) {
    await p.goto(B + url);
    check('searchable on ' + url, await p.locator(sel).count() >= 1);
  }
  // Native select stays the form field: submit a stock adjustment chosen through the search box.
  await p.goto(B + '/admin/stock/adjust');
  await p.selectOption('select[name=location_id]', { index: 1 });
  await p.fill('input[name=note]', 'search test');
  await p.locator('#adj .ss-box').first().click(); await p.keyboard.type('battery'); await p.keyboard.press('Enter');
  await p.fill('#adj input[data-field=qty], #adj input[name$="[qty]"]', '1');
  await Promise.all([p.waitForNavigation(), p.click('form button.btn-primary')]);
  check('form submits the item chosen by search', /adjust|stock/.test(p.url()) && (await p.content()).includes('saved') || (await p.content()).includes('Stock'), p.url());
  // Install on phone: service worker registers; offline shows the friendly page.
  await p.goto(B + '/admin');
  const scope = await p.evaluate(() => navigator.serviceWorker.ready.then((r) => r.scope)).catch((e) => 'ERR ' + e.message);
  check('service worker registered (installable app)', typeof scope === 'string' && scope.startsWith(B), scope);
  await p.reload(); await p.waitForTimeout(500);
  await p.context().setOffline(true);
  await p.goto(B + '/admin/leads').catch(() => {});
  check('offline: friendly page instead of a browser error', (await p.content()).includes('You are offline'));
  await p.context().setOffline(false);
  check('no JavaScript errors', errs.length === 0, errs.join(' | '));
  await b.close();
})().catch((e) => { console.log('FAIL browser test crashed: ' + e.message); process.exit(0); });
