# Sygma Solar — Landing Page

Marketing landing page for **Sygma Solar**, a solar management platform (plant monitoring, O&M, project/EPC management, CRM, inventory and PPA billing).

Plain HTML/CSS/JS — no build step, no dependencies (Inter font loaded from Google Fonts).

```
index.html            # page markup
assets/css/style.css  # styles (responsive, reduced-motion aware)
assets/js/main.js     # nav, tabs, counters, pricing toggle, form validation
assets/img/favicon.svg
```

## Run locally

Open `index.html` in a browser, or serve the folder:

```bash
python3 -m http.server 8080
```

## Customise

- **Brand colours**: CSS variables at the top of `assets/css/style.css` (`--navy`, `--amber`, `--orange`, …).
- **Prices**: `data-monthly` / `data-yearly` attributes on `.amt` elements in the Pricing section.
- **Stats & testimonials**: placeholder figures — replace with your real numbers and customer quotes before going live.
- **Demo form**: currently validates client-side only. Hook it up to your backend/CRM at the `TODO` in `assets/js/main.js` (e.g. a CodeIgniter controller endpoint).
