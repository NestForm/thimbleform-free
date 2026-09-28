/**
 * Capture reproducible Thimbleform Free + Pro screenshots.
 *
 * Credentials via env: NF_USER, NF_PASS
 * Or a Playwright cookie JSON file: NF_COOKIE_FILE
 * Optional: NF_BASE, NF_OUT, NF_COPY_OUT, NF_BUILDER_ID, NF_PRO_FORM_ID
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.NF_BASE || 'https://wordpress-custom.local';
const USER = process.env.NF_USER;
const PASS = process.env.NF_PASS;
const COOKIE_FILE = process.env.NF_COOKIE_FILE || '';
const OUT = process.env.NF_OUT || path.join(__dirname, '..', 'assets');
const COPY_OUT = process.env.NF_COPY_OUT || '';
const BUILDER_ID = process.env.NF_BUILDER_ID || '556';
const PRO_FORM_ID = process.env.NF_PRO_FORM_ID || '756';
const SHOT_FILTER = new Set(
  (process.env.NF_SHOTS || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean),
);

if ((!USER || !PASS) && !COOKIE_FILE) {
  console.error('NF_USER and NF_PASS, or NF_COOKIE_FILE, required');
  process.exit(1);
}

const admin = (route) => `${BASE}/wp-admin/${route}`;
const builder = (id = BUILDER_ID) => admin(`post.php?post=${id}&action=edit`);

const CHROME_CSS = `
  #wpadminbar,
  #wpfooter,
  .fs-notice,
  .notice,
  .update-nag,
  .thimbleform-app__pro,
  .thimbleform-pro-teaser,
  .thimbleform-review-request { display: none !important; visibility: hidden !important; }
  html.wp-toolbar,
  html,
  body.admin-bar,
  body.admin-bar #wpcontent,
  body.admin-bar #wpbody,
  body.admin-bar #wpbody-content,
  #wpwrap,
  #wpcontent { margin-top: 0 !important; padding-top: 0 !important; }
  .thimbleform-app { min-height: 100vh !important; }
`;

async function click(page, selector) {
  const locator = page.locator(selector).first();
  if (!(await locator.count())) {
    throw new Error(`Missing control: ${selector}`);
  }
  await locator.click();
  await page.waitForTimeout(350);
}

async function builderTab(page, tab, subtab = '') {
  await click(page, `[data-thimbleform-tab="${tab}"]`);
  if (subtab) {
    await click(page, `[data-thimbleform-subtab="${subtab}"]`);
  }
}

async function openDetails(page, selector) {
  const locator = page.locator(selector).first();
  if (!(await locator.count())) {
    throw new Error(`Missing details: ${selector}`);
  }
  await locator.evaluate((element) => {
    element.open = true;
  });
  await page.waitForTimeout(250);
}

async function openFirstField(page) {
  const field = page.locator('[data-thimbleform-field]:not([data-field-type="heading"]):not([data-field-type="paragraph"]):not([data-field-type="spacer"])').first();
  if (!(await field.count())) {
    throw new Error('No editable field card found');
  }
  const body = field.locator('[data-thimbleform-card-body]');
  if (await body.isHidden()) {
    await field.locator('[data-thimbleform-toggle]').first().click();
    await page.waitForTimeout(250);
  }
  return field;
}

async function openMoreFields(page) {
  await click(page, '[data-thimbleform-add-menu="more"] [data-thimbleform-add-menu-toggle]');
}

const overviewShots = [
  {
    file: 'screenshot-1.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-dashboard'),
    target: '.thimbleform-app',
  },
  {
    file: 'screenshot-2.png',
    url: admin('edit.php?post_type=thimbleform'),
    target: '.thimbleform-app, #wpbody-content',
  },
  {
    file: 'screenshot-3.png',
    url: builder(),
    target: '[data-thimbleform-admin]',
  },
  {
    file: 'screenshot-4.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-entries'),
    target: '.thimbleform-app',
  },
  {
    file: 'screenshot-5.png',
    url: `${BASE}/#support`,
    target: '[data-block="thimbleform-support"], .thimbleform-support',
    front: true,
  },
  {
    file: 'screenshot-6.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-integrations'),
    target: '.thimbleform-app, #wpbody-content',
  },
  {
    file: 'screenshot-7.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-admin]',
  },
];

const documentationShots = [
  {
    file: 'docs/forms-new-form.png',
    url: admin('edit.php?post_type=thimbleform'),
    target: '.thimbleform-app, #wpbody-content',
  },
  {
    file: 'docs/builder-fields.png',
    url: builder(),
    target: '[data-thimbleform-panel="fields"]',
    prepare: async (page) => {
      await builderTab(page, 'fields');
      await openFirstField(page);
    },
  },
  {
    file: 'docs/conditional-logic.png',
    url: builder(),
    target: '[data-thimbleform-field]:has([data-thimbleform-section="condition"][open])',
    prepare: async (page) => {
      await builderTab(page, 'fields');
      const field = await openFirstField(page);
      await openDetails(page, '[data-thimbleform-field]:not([data-field-type="heading"]):not([data-field-type="paragraph"]):not([data-field-type="spacer"]) [data-thimbleform-section="condition"]');
      await field.scrollIntoViewIfNeeded();
    },
  },
  {
    file: 'docs/publish-embed.png',
    url: builder(),
    target: '.thimbleform-editor__sidebar, .thimbleform-embed',
  },
  {
    file: 'docs/entries-inbox.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-entries'),
    target: '.thimbleform-app',
  },
  {
    file: 'docs/export-controls.png',
    url: admin(`edit.php?post_type=thimbleform_entry&thimbleform_form_id=${BUILDER_ID}`),
    target: '.thimbleform-export-menu__panel',
    prepare: async (page) => {
      await click(page, '.thimbleform-export-menu__toggle');
      await page.locator('.thimbleform-export-menu__panel').first().waitFor({ state: 'visible', timeout: 5000 });
    },
  },
  {
    file: 'docs/mail-settings.png',
    url: builder(),
    target: '[data-thimbleform-panel="mail"]',
    prepare: async (page) => builderTab(page, 'mail', 'notification'),
  },
  {
    file: 'docs/security-settings.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-settings&section=security'),
    target: '.thimbleform-app',
  },
  {
    file: 'docs/captcha-integrations.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-integrations&section=captcha'),
    target: '.thimbleform-app',
  },
  {
    file: 'docs/webhooks.png',
    url: builder(),
    target: '[data-thimbleform-subpanel="webhooks"]',
    prepare: async (page) => builderTab(page, 'settings', 'webhooks'),
  },
  {
    file: 'docs/dashboard.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-dashboard'),
    target: '.thimbleform-app',
  },
  {
    file: 'docs/license.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-pro-license'),
    target: '.thimbleform-app',
  },
  {
    file: 'docs/multi-step-branching.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-steps-setup]',
    prepare: async (page) => {
      await builderTab(page, 'fields');
      const toggle = page.locator('[data-thimbleform-enable-steps]').first();
      if (!(await toggle.isChecked())) {
        await toggle.check({ force: true });
        await page.waitForTimeout(450);
      }
      await openDetails(page, '[data-thimbleform-steps-branch]');
    },
  },
  {
    file: 'docs/quiz-survey.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-subpanel="quiz"]',
    prepare: async (page) => builderTab(page, 'settings', 'quiz'),
  },
  {
    file: 'docs/advanced-fields.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-add-menu="more"] [data-thimbleform-add-menu-panel]',
    prepare: async (page) => {
      await builderTab(page, 'fields');
      await openMoreFields(page);
      await page.locator('[data-thimbleform-add-menu="more"] [data-thimbleform-add-menu-panel]').evaluate((panel) => {
        panel.scrollTop = panel.scrollHeight;
      });
    },
  },
  {
    file: 'docs/automations.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-subpanel="automations"]',
    prepare: async (page) => builderTab(page, 'settings', 'automations'),
  },
  {
    file: 'docs/stripe-payments.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-subpanel="payments"]',
    prepare: async (page) => builderTab(page, 'settings', 'payments'),
  },
  {
    file: 'docs/hubspot.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-subpanel="hubspot"]',
    prepare: async (page) => builderTab(page, 'settings', 'hubspot'),
  },
  {
    file: 'docs/html-email-pdf.png',
    url: builder(PRO_FORM_ID),
    target: '[data-thimbleform-mail-designer]',
    prepare: async (page) => builderTab(page, 'mail', 'notification'),
  },
  {
    file: 'docs/insights.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-dashboard'),
    target: '[data-thimbleform-lead-insights], [data-thimbleform-survey-charts-root], .thimbleform-dash',
  },
  {
    file: 'docs/recruiting.png',
    url: admin('edit.php?post_type=thimbleform&page=thimbleform-recruiting'),
    target: '.thimbleform-app',
  },
];

const allShots = [...overviewShots, ...documentationShots];
const shots = allShots.filter(
  (shot) => SHOT_FILTER.size === 0 || SHOT_FILTER.has(shot.file),
);

function mirrorScreenshot(target, file) {
  if (!COPY_OUT || file.startsWith('docs/')) return;
  const mirror = path.join(COPY_OUT, file);
  fs.mkdirSync(path.dirname(mirror), { recursive: true });
  fs.copyFileSync(target, mirror);
}

async function hideChrome(page) {
  await page.addStyleTag({ content: CHROME_CSS }).catch(() => {});
}

async function scrubPii(page) {
  await page.evaluate(() => {
    const demos = [
      ['Alex Rivera', 'alex@example.com'],
      ['Sam Chen', 'sam@example.com'],
      ['Jordan Lee', 'jordan@example.com'],
      ['Taylor Brooks', 'taylor@example.com'],
      ['Casey Morgan', 'casey@example.com'],
      ['Riley Quinn', 'riley@example.com'],
      ['Jamie Ortiz', 'jamie@example.com'],
      ['Drew Patel', 'drew@example.com'],
    ];
    let index = 0;
    const next = () => demos[index++ % demos.length];
    const emailPattern = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi;
    const localDomainPattern = /(?:https?:\/\/)?(?:www\.)?wordpress-custom\.local(?:\/[^\s<]*)?/gi;
    const sensitivePattern = /(license|secret|api[\s_-]?key|private[\s_-]?key|token)/i;

    document.querySelectorAll('.thimbleform-dash__card, .thimbleform-entries__row').forEach((row) => {
      const demo = next();
      row.querySelectorAll('.thimbleform-dash__card-who, .thimbleform-entries__who').forEach((node) => {
        node.textContent = demo[0];
      });
      row.querySelectorAll('.thimbleform-dash__card-email, .thimbleform-entries__email').forEach((node) => {
        node.textContent = demo[1];
      });
      row.setAttribute('data-thimbleform-scrubbed', '1');
    });

    document.querySelectorAll('input, textarea').forEach((input) => {
      const descriptor = `${input.name || ''} ${input.id || ''} ${input.placeholder || ''}`;
      if (input.type === 'email' || emailPattern.test(input.value || '')) {
        input.value = next()[1];
      } else if (input.type === 'url' || localDomainPattern.test(input.value || '')) {
        input.value = 'https://hooks.example.com/thimbleform';
      } else if (input.type === 'password' || sensitivePattern.test(descriptor)) {
        input.value = '••••••••••••••••';
      }
      emailPattern.lastIndex = 0;
      localDomainPattern.lastIndex = 0;
    });

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    let node;
    while ((node = walker.nextNode())) nodes.push(node);
    nodes.forEach((textNode) => {
      if (textNode.parentElement?.closest('[data-thimbleform-scrubbed]')) return;
      let value = textNode.nodeValue || '';
      value = value.replace(emailPattern, 'alex@example.com');
      value = value.replace(localDomainPattern, 'example.com');
      textNode.nodeValue = value;
    });

    document.querySelectorAll('[class*="license-key"], [data-license-key]').forEach((node) => {
      if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
        node.value = '••••••••••••••••';
      } else {
        node.textContent = '••••••••••••••••';
      }
    });

    document.querySelectorAll('.thimbleform-dash__domains-name').forEach((node, itemIndex) => {
      node.textContent = itemIndex === 0 ? 'example.com' : `demo-${itemIndex + 1}.example`;
    });
    document.querySelectorAll('.thimbleform-entries-insights__value').forEach((node) => {
      node.textContent = 'example.com';
    });

    document.querySelectorAll('tr').forEach((row) => {
      const label = row.querySelector('th, td:first-child')?.textContent?.trim() || '';
      const cells = row.querySelectorAll('td');
      const value = cells.length > 1 ? cells[1] : cells[0];
      if (!value) return;
      if (/^Name:?$/i.test(label)) value.textContent = 'Alex Rivera';
      if (/^(User ID|Site ID):?$/i.test(label)) value.textContent = '0000000';
      if (/^(Public Key|Secret Key|License Key):?$/i.test(label)) value.textContent = '••••••••••••••••';
    });
  });
  await page.waitForTimeout(250);
}

async function locateTarget(page, selector) {
  for (const candidate of selector.split(',').map((item) => item.trim())) {
    const locator = page.locator(candidate).first();
    if ((await locator.count()) && (await locator.isVisible())) {
      return locator;
    }
  }
  throw new Error(`Missing screenshot target: ${selector}`);
}

async function captureTarget(page, shot, targetPath) {
  const target = await locateTarget(page, shot.target);
  await target.scrollIntoViewIfNeeded();
  await page.waitForTimeout(350);
  const box = await target.boundingBox();
  if (!box) throw new Error(`No screenshot bounds: ${shot.target}`);

  const viewport = page.viewportSize();
  const width = Math.min(Math.ceil(box.width), viewport.width - 32);
  const height = Math.min(Math.ceil(box.height), viewport.height - 32);
  const x = Math.max(0, Math.min(Math.floor(box.x), viewport.width - width));
  const y = Math.max(0, Math.min(Math.floor(box.y), viewport.height - height));

  await page.screenshot({
    path: targetPath,
    clip: { x, y, width, height },
  });
}

(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch({
    headless: true,
    channel: 'chrome',
  });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 920 },
    deviceScaleFactor: 2,
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  if (COOKIE_FILE) {
    const cookies = JSON.parse(fs.readFileSync(COOKIE_FILE, 'utf8'));
    await context.addCookies(cookies);
    await page.goto(admin('edit.php?post_type=thimbleform'), { waitUntil: 'domcontentloaded', timeout: 90000 });
  } else {
    await page.goto(`${BASE}/login/`, { waitUntil: 'networkidle', timeout: 90000 });
    const userSelector = (await page.locator('#auth-log').count()) ? '#auth-log' : '#user_login';
    const passSelector = (await page.locator('#auth-pwd').count()) ? '#auth-pwd' : '#user_pass';
    const submitSelector = (await page.locator('button.auth__submit, button[type="submit"]').count())
      ? 'button.auth__submit, button[type="submit"]'
      : '#wp-submit';
    await page.fill(userSelector, USER);
    await page.fill(passSelector, PASS);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 60000 }),
      page.click(submitSelector),
    ]);
  }

  if (page.url().includes('/login') || page.url().includes('wp-login.php')) {
    throw new Error(`Login failed: ${page.url()}`);
  }

  const manifest = [];
  for (const shot of shots) {
    const targetPath = path.join(OUT, shot.file);
    fs.mkdirSync(path.dirname(targetPath), { recursive: true });
    try {
      await page.goto(shot.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await page.waitForTimeout(shot.front ? 3500 : 1600);
      await hideChrome(page);
      if (shot.prepare) await shot.prepare(page);
      await scrubPii(page);
      await captureTarget(page, shot, targetPath);
      mirrorScreenshot(targetPath, shot.file);
      manifest.push({ file: shot.file, url: shot.url, target: shot.target, status: 'captured' });
      console.log('saved', targetPath);
    } catch (error) {
      manifest.push({ file: shot.file, url: shot.url, target: shot.target, status: 'skipped', error: error.message });
      console.error('skipped', shot.file, error.message);
    }
  }

  const manifestPath = path.join(__dirname, 'screenshot-manifest.json');
  let manifestOutput = manifest;
  if (SHOT_FILTER.size > 0 && fs.existsSync(manifestPath)) {
    const previous = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    const merged = new Map(previous.map((item) => [item.file, item]));
    manifest.forEach((item) => merged.set(item.file, item));
    manifestOutput = allShots.map((shot) => merged.get(shot.file)).filter(Boolean);
  }
  fs.writeFileSync(manifestPath, `${JSON.stringify(manifestOutput, null, 2)}\n`);
  await browser.close();

  const skipped = manifest.filter((item) => item.status !== 'captured');
  console.log(`done: ${manifest.length - skipped.length} captured, ${skipped.length} skipped`);
  if (skipped.length) process.exitCode = 2;
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
