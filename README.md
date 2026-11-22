# Sygma Solar — Landing Page

Marketing landing page for **Sygma Solar**, an all-in-one software platform for solar businesses: CRM, AI rooftop design, proposals, project management, procurement & inventory, O&M, plant monitoring and finance/billing.

Plain HTML/CSS/JS — no build step, no dependencies (fonts from Google Fonts).

```
index.html            # page markup
assets/css/style.css  # styles (responsive 320px → desktop, reduced-motion aware)
assets/js/main.js     # menus, animations, calculator, carousel, forms
assets/img/favicon.svg
```

## Sections

Announcement bar · mega-menu header · hero with quick lead form and dashboard mockup · trust badges ·
integrations marquee · 8-module product grid · Design / Proposals / Projects deep-dives ·
Monitoring / O&M / Inventory / Finance tabs · impact stats · product-tour video + onboarding steps ·
**interactive rooftop savings calculator** · audiences · financing · mobile apps · testimonials carousel ·
security · pricing (₹, monthly/yearly) · FAQ · demo booking form · footer with newsletter.

## Run locally

```bash
python3 -m http.server 8080
```

Then open http://localhost:8080 (or just open `index.html` directly).

## Before going live

- **Placeholder content**: impact numbers, testimonials, prices, phone number and social links are placeholders — replace with real data.
- **Forms**: the demo form, hero quick form and newsletter only validate in the browser. Wire them to your backend/CRM at the `TODO` comments in `assets/js/main.js` (e.g. a CodeIgniter controller endpoint).
- **Product video**: set `data-src` on `#video-frame` in `index.html` to a YouTube embed URL (`https://www.youtube.com/embed/VIDEO_ID`). Until then a "coming soon" card is shown.
- **Calculator assumptions** (`assets/js/main.js`): subsidy slabs, 0.82 kg CO₂/kWh grid factor, 0.5%/yr degradation and 3%/yr tariff escalation — adjust to your market.
- **Brand colours**: CSS variables at the top of `assets/css/style.css`.
