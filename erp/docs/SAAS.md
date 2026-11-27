# Multi-company (SaaS) mode and the owner control panel

One copy of the ERP serves many solar companies. Each company:

- opens the ERP at **its own address**, e.g. `https://sunpower.sygmasolar.in/`
  (or its own domain, e.g. `https://erp.sunpower.com/`);
- has **its own data**: its own tables in the database (`t_sunpower_leads`,
  `t_sunpower_invoices`, …) and its own upload folder (`writable/uploads/tenants/sunpower/`);
- has its own users, roles, settings, logo, modules and plan.

You manage all companies in the **owner control panel** at your own address,
e.g. `https://admin.sygmasolar.in/owner`.

Without `saas.enabled` the ERP works as a single company, exactly as before.
The current demo (`sygma.instivision.com/erp`) stays a single-company install.

## What the owner panel does

| Screen | What you can do |
|---|---|
| **Companies** | All companies with status, plan, paid-until date and last use. Counters for active, trial, expiring in 15 days, expired, suspended. Search. |
| **New company** | Name, code, web address, contact, GSTIN, state, plan, paid-until date, maximum users, modules, first Super Admin email and password. Creates all the company's tables in a few seconds. |
| **Company page** | Change details, address, plan, status (active / trial / suspended with your message), paid-until date, maximum users, modules and options. See usage (users, leads, customers, quotations, invoices, projects, sales), database status and history. |
| **Open as Super Admin** | Opens that company's ERP signed in as its Super Admin, for support. The link works once, for 2 minutes, and is recorded in the company's User Log. |
| **Super Admin login** | Set a new email / password when a client is locked out. |
| **Database updates** | For one company, or **Database updates for all** after each new version. |
| **Delete company** | Only when suspended, and you type its code. Removes all its tables and files. |
| **Activity** | Every owner sign-in, company created / changed / deleted, support login, update. |
| **Owner logins** | Change your password; add a partner who may use the panel. |

What the company sees:

- **Suspended**: a page "Account on hold" with your message, instead of the ERP.
- **After the paid-until date**: "Subscription ended … your data is safe".
- **10 days before the end**: a reminder at the top of the ERP for their administrators.
- **User limit reached**: they cannot add or re-activate a user ("Your plan allows N active users").
- Modules you switched off are not in their menu; their own Super Admin cannot switch them on.

## Setting it up on Hostinger (once)

Example names: main domain `sygmasolar.in`, owner panel `admin.sygmasolar.in`,
companies `<code>.sygmasolar.in`.

1. **Domain**: add `sygmasolar.in` to your Hostinger plan.
2. **Files**: deploy this repository with Git to the domain's `public_html`, as you did for
   the demo. Upload `vendor/` into `public_html/erp/` (or run `composer install --no-dev` there).
3. **Database**: hPanel → Databases → create a **new, empty** MySQL database and user
   (for example `u123_saas`). Do not reuse the demo database. You do not import anything:
   the ERP creates the tables itself.
4. **Wildcard sub-domain** (once): hPanel → Domains → Sub-domains → create `*` (shown as
   `*.sygmasolar.in`) with **custom folder** `public_html/erp`. This one entry serves the owner
   panel (`admin.sygmasolar.in`) and every company (`sunpower.sygmasolar.in`, …), so you never
   add sub-domains again. Then hPanel → Security → SSL: install SSL for `*.sygmasolar.in`
   (wildcard certificate). Open `https://admin.sygmasolar.in` once to check the padlock.
   A sub-domain that has no company shows "Company not found".
5. **`.env`**: File Manager → `public_html/erp/.env`:

   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'https://admin.sygmasolar.in/'
   app.forceGlobalSecureRequests = true
   encryption.key = hex2bin:PUT_64_HEX_CHARS_HERE

   database.default.hostname = localhost
   database.default.database = u123_saas
   database.default.username = u123_saas
   database.default.password = 'the-database-password'
   database.default.DBDriver = MySQLi

   saas.enabled       = true
   saas.ownerHosts    = 'admin.sygmasolar.in'
   saas.baseDomain    = 'sygmasolar.in'
   saas.ownerEmail    = 'you@example.com'
   saas.ownerPassword = 'Choose-A-Long-Password'

   email.fromEmail = 'noreply@sygmasolar.in'
   email.fromName  = 'Sygma Solar Suite'
   email.protocol  = smtp
   email.SMTPHost  = 'smtp.hostinger.com'
   email.SMTPUser  = 'noreply@sygmasolar.in'
   email.SMTPPass  = 'mailbox-password'
   email.SMTPPort  = 465
   email.SMTPCrypto = ssl
   ```

   Keep `features.manage` and `demo.mode` **out** of this `.env`.
6. Open `https://admin.sygmasolar.in/owner`, sign in with `saas.ownerEmail` /
   `saas.ownerPassword`. Your owner login is created. **Then delete the `saas.ownerPassword`
   line from `.env`**, and change the password in **Owner logins** if you like.
7. **Cron jobs** (hPanel → Advanced → Cron Jobs). Each runs for every active company:
   - every minute: `/usr/bin/php /home/u123/domains/sygmasolar.in/public_html/erp/spark saas:run "queue:work emails --stop-when-empty"`
   - every minute: `/usr/bin/php /home/u123/domains/sygmasolar.in/public_html/erp/spark saas:run "queue:work whatsapp --stop-when-empty"`
   - daily 08:00: `/usr/bin/php /home/u123/domains/sygmasolar.in/public_html/erp/spark saas:run "amc:remind"`
   - every 10 minutes (if any company uses IndiaMART): `/usr/bin/php /home/u123/domains/sygmasolar.in/public_html/erp/spark saas:run "indiamart:pull"`

## Onboarding a client

1. Owner panel → **New company**: name, code (e.g. `sunpower`; the address fills itself),
   contact, GSTIN, state, plan, paid-until date, maximum users, modules, and the client's
   Super Admin email + password (**Generate** makes one).
2. **Create company**. The address works at once (wildcard sub-domain); nothing to do in hPanel. Then **Open login page** to check it, or **Open as Super Admin** to fill
   their settings (logo, bank, quotation terms, users) with them.
3. Send the client: the address, the Super Admin email and password. They change the password
   in their profile and add their own staff in Administration → Users.

**Client's own domain** (e.g. `erp.sunpower.com`): the client points it to your Hostinger
server (A record / CNAME as hPanel shows), you add it in hPanel pointing to `public_html/erp`,
and enter it as **Second address** on the company page.

## After each new version

Merge to main (Git deploy updates the files) → owner panel → **Database updates for all**.
Take a database backup first (hPanel → Databases → phpMyAdmin → Export, or Hostinger backups).

## Command line (optional, needs SSH)

```bash
php spark saas:list
```

```bash
php spark saas:create --code=sunpower --name="Sun Power" --domain=sunpower.sygmasolar.in --admin-email=boss@sunpower.com --admin-password='Strong#Pass1' --off=amc,design --expires=2027-03-31 --max-users=10
```

```bash
php spark saas:migrate
```

```bash
php spark saas:run "amc:remind" --company=sunpower
```

```bash
php spark saas:owner you@example.com 'New-Long-Password' "Your Name"
```

In multi-company mode, company commands (`migrate`, `amc:remind`, `queue:work`, …) must go
through `saas:run` / `saas:migrate`; run directly they are refused, so no tables are ever
created without a company prefix.

## How companies are kept apart

- The company is chosen from the web address before anything reads the database; an unknown
  address gets "Company not found".
- Every table of a company has its prefix. The database holds no app table without a prefix,
  so a query that forgot it fails instead of reaching another company's data.
- Uploads, cache and the login session are per company; a session from one company is not
  accepted by another.
- Tests: `tests/e2e/full_flow.sh` runs all 449 business checks inside a company
  (`E2E_SAAS=1`), and `tests/e2e/saas_flow.sh` checks the owner panel and that two companies
  never see each other's data, files or logins.
