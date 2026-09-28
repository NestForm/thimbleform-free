# Thimbleform

Build **forms, quizzes and surveys** for WordPress that convert.

Not a Contact Form 7 clone — a focused product around:

**Forms · Quizzes · Surveys · Leads · Analytics**

**Path:** `wp-content/plugins/thimbleform/`  
**Main file:** `thimbleform.php`  
**Version:** 2.2.1  
**Pro add-on:** `wp-content/plugins/nestform-pro/` (sold separately on [thimbleform.app](https://thimbleform.app))  
**Source:** [github.com/NestForm/nestform-free](https://github.com/NestForm/nestform-free)

WordPress.org listing copy lives in `readme.txt`.

## Positioning

| Pillar | What users get |
|--------|----------------|
| **Lead forms** | Contact / quote / callback → Entries → Email → (Pro) PDF |
| **Interactive forms** | Multi-step quizzes with scoring, bands, branching, shareable results (Pro) |
| **Survey / feedback** | NPS, matrix, ranking + insights (Pro fields); Free summary of choices and text |

Free = strong lead capture. Pro = interactive conversion flows.

## Free vs Pro

| Free | Pro (Thimbleform Pro + valid license) |
|------|-------------------------------------|
| Unlimited forms | Multi-step + branch rules |
| Basic/layout fields, file uploads, conditionals | Quizzes & surveys (scoring, bands, timer, attempts, share) |
| Entries, CSV, print, captcha, honeypot, mail | Automations |
| Templates on Add New, appearance, JSON + CF7/WPForms import | Advanced analytics / lead insights |
| Dashboard, response summary | Advanced fields (rating, signature, NPS, scale, ranking, matrix) |
| Webhooks | HTML email designer + PDF |
| Light / dark admin, Developers screen | Calculated fields, repeaters |
| Stripe keys in Integrations | Stripe Payment field on forms |

Capabilities are registered only by **nestform-pro** after a valid license from thimbleform.app. Filtering `nestform_is_pro` alone does not unlock gated runtime.

Checkout on thimbleform.app → install **nestform-pro** → activate the key under **Forms → License**.

## Features

- Multiple forms as CPT `nestform` (fields, messages, mail, settings in post meta)
- Admin builder: DnD, quick-add, **Undo**, **Preview** drawer, templates on empty Add New
- **Duplicate form** + starter templates
- Layout blocks: Heading, Image, HTML
- File upload: extensions, max MB, **max files** (multi) + entry previews
- Conditional show/hide fields
- Mail: CC/BCC, autoreply, **extra conditional notification**
- Time-trap spam + optional Akismet
- Entries: CSV export, print view, status (New / Read / Spam)
- Response summary (KPIs, most chosen / skipped, field cards)
- Captcha via **Forms → Integrations** (reCAPTCHA v2/v3)
- Plugin Settings: email defaults, entry date format, uninstall cleanup, admin theme
- Form JSON import / export; import from Contact Form 7 and WPForms
- Message packs EN/RU, a11y on steps/errors
- Hooks: `nestform_loaded`, `nestform_submitted`, `nestform_mail_sent`, `nestform_webhook_payload`, …

## Validation (built-in)

| Check | Where |
|-------|--------|
| Required | server + light HTML5 hints |
| Email / phone / URL / number / date / time | server (+ front hints) |
| Select must match options | server |
| Length caps (text 500 / textarea 10k) | server |
| Nonce + honeypot + rate limit | server |

## Release zip

From the plugin root:

```bash
python bin/build-release.py
```

Uses `.distignore` (no Freemius vendor, no dev tooling). `README.md` is excluded from the zip; `readme.txt` ships to WordPress.org.
