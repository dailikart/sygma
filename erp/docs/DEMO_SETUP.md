# Sygma Solar Suite: team demo setup

The website (`../index.html`) and the ERP (this `erp/` folder) are deployed together:

```
public_html/            ← the sygma repo
├── index.html          ← marketing website  (https://yourdomain.com/)
├── assets/
└── erp/                ← this ERP           (https://yourdomain.com/erp/)
    ├── .htaccess       ← sends requests to erp/public/, blocks app/, vendor/, writable/, docs/, .env
    ├── public/
    └── docs/database/sygma_demo.sql
```

Website: **Log in** → `erp/login`. The demo is for **your team's walkthroughs**: no demo
login is shown on the website or the sign-in page.

## 1. Database

1. Create an empty database (hPanel → Databases, or):
   ```sql
   CREATE DATABASE sygma_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
2. phpMyAdmin → select it → **Import** → `erp/docs/database/sygma_demo.sql` → Go.
   (Command line: `mysql -u USER -p sygma_demo < docs/database/sygma_demo.sql`)
3. Copy `erp/docs/database/demo_uploads/*` into `erp/writable/uploads/` (project photos and
   customer documents). `php spark demo:reset` does this for you.

### Demo logins

Default password for all: `Demo@12345`. **Set your own team password** with `demo.password`
(section 3) so the default stops working.

| Email | Role |
|---|---|
| demo@sygmasolar.com | Super Admin (shown on the login page) |
| ravi@demo.sygmasolar.com | Marketing |
| suresh@demo.sygmasolar.com | Surveyor |
| store1@demo.sygmasolar.com | Store |
| driver1@demo.sygmasolar.com | Delivery |
| fitter1@demo.sygmasolar.com | Installer |
| eb1@demo.sygmasolar.com | EB / Liaison |
| acct1@demo.sygmasolar.com | Accounts |
| service1@demo.sygmasolar.com | Service / AMC |
| mgr@demo.sygmasolar.com | Admin (manager) |
| kumar@demo.sygmasolar.com | Store + Delivery |

### What the demo data contains

Leads (open, interested, lost, converted), B2B and B2C customers with KYC documents,
6 invoices (subsidy / non-subsidy, CGST+SGST and IGST, bank loan with disbursements),
part and full payments, 2 completed projects and 4 active ones at **survey, dispatch,
installation and DCR**, quotations / proposals (with share link), service complaints,
customer status-page links, leads from IndiaMART / Facebook / Google Ads, purchases,
purchase return, supplier payments, stock in two locations, AMC contracts with visits,
income / expense entries and the full ledger. The IndiaMART / Meta / Google
integrations are switched off in the demo (no keys).

## 2. `erp/.env`

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://yourdomain.com/erp/'
app.forceGlobalSecureRequests = true
encryption.key = hex2bin:PUT_64_HEX_CHARS_HERE
database.default.hostname = localhost
database.default.database = sygma_demo
database.default.username = your_db_user
database.default.password = 'your-db-password'
database.default.DBDriver = MySQLi
database.default.port = 3306
demo.mode = true
demo.password = 'your-private-team-password'
features.manage = true
```

`features.manage = true` shows **Administration → Modules & Options**, so in a demo you can
switch modules off to show a client exactly their package ([MODULES.md](MODULES.md)).

Then install the PHP packages inside `erp/` (`composer install --no-dev`, or upload `vendor/`)
and make `erp/writable/` writable. Everything else (PHP version, extensions) is as in
[SETUP.md](SETUP.md).

## 3. Demo mode (`demo.mode = true`)

- **Private team password:** with `demo.password` set, every demo login uses it. It is applied
  automatically on the next page load after you add or change it (no SSH or command needed),
  and again after a demo reset. `php spark demo:password` does the same from the command line.
- A "Demo company" banner is shown inside the ERP.
- Visitors can try everything (leads, quotations, invoices, payments, projects, service
  complaints, stock, AMC, reports), but **profile/password, users, roles, settings,
  WhatsApp templates, IndiaMART fetch, bulk import and database updates are view-only**, so nobody can lock the demo or change server settings.
- **Demo data stays**: what your team enters is kept. To go back to the original sample data,
  open **Administration → Database Updates → Reset demo data** (no SSH needed). It also clears
  module choices and uploads, and keeps your team password. From the command line:
  `php spark demo:reset`. Both refuse to run unless `demo.mode = true`. **Never set
  demo.mode on a customer's server.**

For a real customer installation leave `demo.mode` out and use `docs/database/solar_crm_schema.sql`
(empty database, one admin) as described in [SETUP.md](SETUP.md).

## 4. Rebuilding the demo database after code changes

When a new migration is added, rebuild the demo SQL so it matches the code
(needs a local MySQL/MariaDB user that can create databases, default `solar` / `solarpw`):

```bash
tests/e2e/full_flow.sh
```

```bash
docs/database/demo_extra.sh
```

```bash
docs/database/build_demo_sql.sh
```

The first runs the whole app end to end (397 checks), the second adds open leads and
active projects, the third cleans test values and writes `sygma_demo.sql` + `demo_uploads/`.
