# Sygma Solar Suite — Landing Page

Marketing landing page for **Sygma Solar Suite**, an all-in-one software platform for solar businesses: CRM, AI rooftop design, proposals, project management, procurement & inventory, O&M, plant monitoring and finance/billing.

The website is plain HTML/CSS/JS (no build step). The ERP behind **Log in** and
**the team demo** is in [`erp/`](erp/) (CodeIgniter 4, PHP 8.2+, MySQL).

```
erp/                  # the ERP, served at /erp/  → setup: erp/docs/DEMO_SETUP.md
index.html            # page markup
assets/css/style.css  # styles (responsive 320px → desktop, reduced-motion aware)
assets/js/main.js     # menus, animations, calculator, carousel, forms
assets/img/favicon.svg
```

## Sections

Announcement bar · mega-menu header (Products / Solutions / Resources / FAQ, Log in) · hero with callback form and
dashboard mockup · trust badges · integrations marquee (IndiaMART, Meta, Google Ads, WhatsApp, Tally, Excel, GST) ·
8-module grid · Leads & CRM / Quotations / Project workflow deep-dives · Inventory & purchase / AMC & service /
Accounts & Tally / GST billing tabs · product facts · onboarding steps · rooftop savings calculator · audiences ·
subsidy, loan & profit tracking · phone & customer status page · security & control · FAQ · demo booking form · footer.

Every feature on the page exists in the ERP (`erp/`). There is no pricing section and no testimonials —
add real customer quotes later if you have them.

## Run locally

```bash
python3 -m http.server 8080
```

Then open http://localhost:8080 (or just open `index.html` directly).

## Login and team demo

- **Log in** (header) and **Customer login** (footer) → `erp/login`.
- The demo is for your team's walkthroughs only: no demo login is shown anywhere on the site.
  Demo links on the page go to the **Book a demo** form.
- Demo database and setup (`.env`, private team password, nightly reset):
  [erp/docs/DEMO_SETUP.md](erp/docs/DEMO_SETUP.md).

Upload the whole repo to `public_html`: the website is served at `/` and the ERP at `/erp/`.

## Before going live

- **Placeholder content**: phone number and social links are placeholders — replace with real data.
- **Forms**: the demo form, hero quick form and newsletter only validate in the browser. Wire them to your backend/CRM at the `TODO` comments in `assets/js/main.js` (e.g. a CodeIgniter controller endpoint).
- **Product video**: set `data-src` on `#video-frame` in `index.html` to a YouTube embed URL (`https://www.youtube.com/embed/VIDEO_ID`). Until then a "coming soon" card is shown.
- **Calculator assumptions** (`assets/js/main.js`): subsidy slabs, 0.82 kg CO₂/kWh grid factor, 0.5%/yr degradation and 3%/yr tariff escalation — adjust to your market.
- **Brand colours**: CSS variables at the top of `assets/css/style.css`.
