=== Thimbleform ===
Contributors: nestform
Tags: forms, contact form, lead generation, form builder, email
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build lead and contact forms with an entries inbox, email alerts, spam protection, and webhooks.

== Description ==

Thimbleform is a form builder for lead capture and feedback.

**Builder**

* Unlimited forms, starter templates (open on Add New when the canvas is empty)
* Drag-and-drop fields, undo, live preview
* Conditional show/hide, file uploads, layout blocks (heading, image, HTML)
* Appearance skins and per-form styling
* Duplicate forms, JSON import/export, import from Contact Form 7 and WPForms

**Inbox & mail**

* Entries with New / Read / Spam, star, CSV export, printable entry view
* Response summary: totals, fill rate, most chosen / most skipped answers
* Plain-text notifications, CC/BCC, optional autoreply
* Outbound webhooks (HTTPS endpoints you configure per form)

**Spam & embed**

* Honeypot, time trap, rate limit, optional Akismet
* Google reCAPTCHA v2/v3 via **Thimbleform → Integrations**
* Gutenberg block and shortcode `[thimbleform id="123"]`

**Admin**

* Dashboard with submission charts
* Light / dark admin theme

**Optional Thimbleform Pro** is a **separate add-on** (`thimbleform-pro`), sold via Freemius and hosted outside the WordPress.org directory. Premium code is not included in this download. It adds multi-step flows, quizzes and surveys, Stripe payments, HubSpot sync, advanced fields, HTML email, PDF attachments, automations, and richer analytics. Compare features under **Thimbleform → Pro**.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/thimbleform/` or install from the WordPress plugins screen.
2. Activate **Thimbleform** through the **Plugins** menu.
3. Open **Thimbleform** in the admin menu to create your first form.
4. Embed with the Gutenberg block or shortcode `[thimbleform id="123"]`.
5. Documentation and hooks: [thimbleform.app/docs](https://thimbleform.app/docs).

For Pro features, install the `thimbleform-pro` add-on from Freemius checkout / your purchase email, then activate the license under **Thimbleform → Account** or **Thimbleform → License**.

== Frequently Asked Questions ==

= Is Thimbleform Pro included in this download? =

No. This is the free plugin. Thimbleform Pro is a separate add-on purchased via Freemius and installed as its own plugin.

= How do I activate Pro after purchase? =

Install **Thimbleform Pro**, then open **Thimbleform → Account** (or **Thimbleform → License**) and activate your Freemius license on this site.

= How many forms can I create? =

Unlimited on the free plugin. Pro adds builder features (multi-step, quizzes, advanced fields, and more), not extra form slots.

= Does Thimbleform store submissions? =

Yes. Submissions appear under **Thimbleform → Entries**. You can export CSV and print a single entry.

= Does Thimbleform add branding to my public site? =

No. A “Powered by Thimbleform” link stays off unless you turn it on under **Thimbleform → Settings**.

= Where can I read the source? =

The PHP in this download is the source. CSS and JavaScript are built from the files on GitHub: [github.com/NestForm/thimbleform-free](https://github.com/NestForm/thimbleform-free). From that project, `npm run build` rebuilds the assets.

== External services ==

This plugin can connect to optional third-party services configured by the site administrator:

**Outbound webhooks** (optional)

* Used for: POST JSON to HTTPS endpoints you configure per form (Settings → Webhooks).
* When: after each successful submission, if webhooks are enabled for that form.
* Data sent: form fields, entry metadata, and site URL — only to URLs you enter.

**Google reCAPTCHA** (optional)

* Used for: spam protection on forms.
* When: after you save site and secret keys under **Thimbleform → Integrations**.
* Data sent: challenge response tokens and related anti-spam data per [Google's policies](https://policies.google.com/privacy).
* Terms: https://policies.google.com/terms

**Cloudflare Turnstile** (optional)

* Used for: spam protection on forms (alternative captcha provider).
* When: after you choose Turnstile and save site/secret keys under **Thimbleform → Integrations**.
* Data sent: challenge tokens to Cloudflare per [Cloudflare's policies](https://www.cloudflare.com/privacypolicy/).
* Terms: https://www.cloudflare.com/website-terms/

**hCaptcha** (optional)

* Used for: spam protection on forms (alternative captcha provider).
* When: after you choose hCaptcha and save site/secret keys under **Thimbleform → Integrations**.
* Data sent: challenge tokens to hCaptcha per [hCaptcha's policies](https://www.hcaptcha.com/privacy).
* Terms: https://www.hcaptcha.com/terms

**Akismet** (optional)

* Used for: spam scoring of submissions when the Akismet plugin is installed and configured.
* When: after a form is submitted, if Akismet is available on the site.
* Data sent: form field content and comment-check metadata to Automattic’s Akismet service per [Akismet's privacy policy](https://akismet.com/privacy/).
* Terms: https://akismet.com/tos/

**Stripe** (optional — Thimbleform Pro payment fields)

* Used for: accepting card payments on forms that include a Payment field.
* When: after you enable Stripe and save API keys under **Thimbleform → Integrations**, enable Stripe on the form, and add a Payment field (requires Thimbleform Pro).
* Data sent: payment amounts, currency, and payment intent metadata to Stripe; card details go directly to Stripe (never through Thimbleform servers).
* Terms: https://stripe.com/legal
* Privacy: https://stripe.com/privacy

**HubSpot** (optional — Thimbleform Pro)

* Used for: creating or updating HubSpot CRM contacts from form submissions.
* When: after you enable HubSpot and save a Private App access token under **Thimbleform → Integrations**, enable HubSpot on the form, map fields, and Thimbleform Pro is licensed.
* Data sent: mapped contact properties (typically email, name, phone, company) to HubSpot’s CRM API.
* Terms: https://legal.hubspot.com/terms-of-service
* Privacy: https://legal.hubspot.com/privacy-policy

**thimbleform.app / Freemius** (optional — Pro purchase only)

* Used for: purchasing Thimbleform Pro and managing your Freemius license.
* When: only if you choose to buy Pro (checkout opens Freemius).
* The free plugin does not require a Freemius or thimbleform.app account to run.

== Bundled fonts ==

Admin UI uses self-hosted **Plus Jakarta Sans** and **Sora** (SIL Open Font License 1.1). Font files ship under `assets/fonts/` with `assets/fonts/OFL.txt`. No Google Fonts CDN is used.

== Bundled flags ==

The phone country picker uses self-hosted SVG flags from [flag-icons](https://github.com/lipis/flag-icons) (MIT). Files ship under `assets/flags/` with `assets/flags/LICENSE.txt`. No flag CDN is used.

== Screenshots ==

1. Dashboard — Work pulse, activity chart, and quick actions.
2. Forms list — search, status, and shortcode copy.
3. Form builder — drag-and-drop fields on the canvas.
4. Entries inbox — submissions with status filters.
5. Front-end form — embedded Thimbleform on a page.

== Changelog ==

= 2.3.2 =
* Public name is Thimbleform. The plugin directory slug is `thimbleform`.
* Entries hub: optional All / Forms / Jobs kind filters via `nestform_entries_kind_filters` and `nestform_entries_hub_query_args` (form_ids / exclude_form_ids).
* Sharper dashboard charts (no forced canvas stretch on retina).
* Clearer description and screenshots of the free plugin.

= 2.3.1 =
* Addon hooks: `nestform_accessible_form_ids`, `nestform_user_can_manage_form_entries`, `nestform_templates`, `nestform_template_applied`, `nestform_entry_meta_after`.
* Forms hub respects the same entry access allow-list (needed for Thimbleform HR and similar add-ons).

= 2.3.0 =
* Dashboard redesign: Work pulse (New / Read / Spam), quieter KPIs, Activity chart without duplicate status footers.
* Top forms rail replaces Status mix; Recent activity uses a normal panel frame.
* Quick actions are visible secondary buttons (New form, Forms/Export, Integrations).
* Developers docs: `nestform_dashboard_work_pulse_extra` filter.
* Tested up to WordPress 7.1. Turnstile, hCaptcha, and Akismet are described under External services.
* Smoke checks via `npm test`.

= 2.2.1 =
* Printable entries: two-column label/value layout so answers line up.
* Empty Add New forms open the templates gallery; starter cards stay on the canvas.
* Response summary: KPIs, most chosen / most skipped, richer field cards.
* Unread counts use a ripple; New status uses a quiet border pulse.
* Phone country flags are bundled as local SVGs (no flagcdn.com).

= 2.2.0 =
* Free plugin: unlimited forms, no Freemius SDK in the free zip; Pro sold via Freemius.
* Pro add-on: Freemius license activation.
* Admin preview submits no longer create entries.
* In-app **Pro** page and **Developers** reference.
* Thimbleform Pro is a separate add-on and is not part of this download.

== Upgrade Notice ==

= 2.3.2 =
Entries hub kind filters for Recruiting (All / Forms / Jobs), sharper dashboard charts, and directory screenshots of the free plugin.

= 2.3.1 =
Addon access hooks for Thimbleform HR and similar recruiting/ownership add-ons.

= 2.3.0 =
Clearer dashboard: one inbox pulse, cleaner chart and Top forms, WordPress 7.1 tested.

= 2.2.1 =
Print layout, templates on empty forms, and a clearer response summary.

= 2.2.0 =
Free plugin with unlimited forms. Thimbleform Pro is a separate add-on purchased via Freemius.
