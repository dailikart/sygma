# Solar CRM: Setup Guide

This guide takes you from a fresh checkout to a running app, on your laptop
(XAMPP / WAMP / Laragon) or a Linux server. After setup, follow
[USER_GUIDE.md](USER_GUIDE.md) section 0 for the first-time data setup.

## 1. What is in this repo

| Package | Used for |
|---|---|
| `codeigniter4/framework` 4.7 | The framework |
| `codeigniter4/shield` 1.4 | Login, password reset, roles & permissions |
| `codeigniter4/settings` | Settings saved in the database (company, GST, Tally ledgers, WhatsApp, role matrix) |
| `codeigniter4/queue` 1.0 | Background email and WhatsApp sending |
| `mpdf/mpdf` 8.3 | Invoice, receipt, purchase and dispatch-list PDFs |
| `phpoffice/phpspreadsheet` 5.10 | Spreadsheet support (reports export as Excel) |
| Bootstrap 5.3 + Bootstrap Icons | Bundled in `public/assets/vendor`, so no internet/CDN is needed |

### Modules

- **Access control:** users with several roles each; a role × module permission matrix editable in the app.
- **Marketing:** leads, follow-ups, convert to B2B/B2C customer.
- **Sales:** invoices (subsidy / non-subsidy, kW, GST CGST+SGST or IGST), part payments, receipts (PDF + email + WhatsApp).
- **Workflow:** Survey → Dispatch → Delivery → Installation → EB / Net Meter (PCR) → Subsidy, with stage assignment, rules on what's needed before a stage can be completed, and notifications.
- **Store:** Installation Type → Category → Item; item groups (kits) by kW; dispatch lists; stock by location; in transit.
- **Purchase:** suppliers, purchase orders → purchases (goods received) → purchase returns, supplier payments.
- **Accounts:** double-entry vouchers for every transaction; income, expenses, journal / contra; ledgers, day book, trial balance.
- **AMC:** plans master; contract from the commissioning date; visit schedule and reminders.
- **Reports:** income vs expense, inventory usage B2B/B2C, supplier summary, project status, subsidy status (all export to Excel).
- **Tally:** ledger masters XML + vouchers XML (same format as the school ERP).

The `vendor/` folder and `.env` are **not** committed. Every developer creates
them with the steps below.

## 2. Requirements

- PHP **8.2 or newer** (8.3 recommended) with: `intl`, `mbstring`, `json`,
  `mysqlnd`/`mysqli`, `curl`, `gd`, `zip`, `xml`.
  On XAMPP, open `php.ini`, remove the `;` in front of `extension=intl` and `extension=gd`, then restart Apache.
- Composer 2, MySQL 8 or MariaDB 10.6+, Git.

## 3. Install

```bash
git clone https://github.com/dailikart/solar.git
```

```bash
cd solar
```

```bash
git checkout claude/zealous-curie-jofmpv
```

```bash
composer install
```

## 4. Create the database

```sql
CREATE DATABASE solar_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 5. Create your `.env` file

```bash
cp env .env
```

(On Windows without Git Bash: copy `env` and rename the copy to `.env`.)

Set at least these values. Any value with spaces or special characters must be in
single quotes.

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

database.default.hostname = localhost
database.default.database = solar_crm
database.default.username = root
database.default.password = ''
database.default.DBDriver = MySQLi
database.default.port = 3306

# Sender for receipts and notifications
email.fromEmail = 'noreply@yourcompany.com'
email.fromName  = 'Your Company Solar'
email.protocol  = smtp
email.SMTPHost  = 'smtp.gmail.com'
email.SMTPUser  = 'noreply@yourcompany.com'
email.SMTPPass  = 'your-app-password'
email.SMTPPort  = 587
email.SMTPCrypto = tls

# First admin login (used once by the seeder)
admin.email    = 'you@yourcompany.com'
admin.password = 'Choose-A-Strong-Password'
```

```bash
php spark key:generate
```

## 6. Create the tables and the first admin

```bash
php spark migrate --all
```

```bash
php spark db:seed AdminSeeder
```

After it says *Super Admin … created*, delete `admin.password` from `.env`.

## 7. Run it

```bash
php spark serve
```

Open http://localhost:8080 and log in. **With XAMPP/Apache**, point the virtual host
at the `public/` folder (never the project root) and set `app.baseURL` to that URL.

## 8. Background jobs and daily reminders

Emails and WhatsApp messages are queued so pages stay fast. While developing,
run the workers in separate terminals:

```bash
php spark queue:work emails
```

```bash
php spark queue:work whatsapp
```

On the live server, add these cron entries (`crontab -e`, adjust the path):

```bash
* * * * * cd /var/www/solar && php spark queue:work emails --stop-when-empty >> /dev/null 2>&1
```

```bash
* * * * * cd /var/www/solar && php spark queue:work whatsapp --stop-when-empty >> /dev/null 2>&1
```

```bash
0 8 * * * cd /var/www/solar && php spark amc:remind >> /dev/null 2>&1
```

`amc:remind` notifies the service team and the customer before each AMC visit
(days set in Settings → AMC) and marks expired AMC contracts.

## 9. WhatsApp (WeBase Cloud API)

Same provider and API as the school ERP (`Webase_whatsapp`).

1. **Settings → WhatsApp**: switch on, base URL (`https://whatsappcloud.webase.co.in/api`),
   vendor UID, API token.
2. **Administration → WhatsApp Templates**: for each event (payment received,
   material dispatched, delivered, installation completed, commissioned, AMC visit
   due…), enter the approved template name and the order of variables, e.g.
   `customer_name,amount,invoice_no,balance` → `field_1, field_2…`.
3. **Administration → Message Log** shows every message sent and the API reply.

## 10. Tally

1. **Settings → Tally ledger names**: the sales, purchase and GST ledger names exactly as in
   your Tally company (`{rate}` becomes 6, 9, 12, 18…).
2. **Reports → Tally Export → Ledger masters XML**, then in Tally: *Import → Masters* (once).
3. **Vouchers XML** for the period, then in Tally: *Import → Transactions*.

## 11. Useful commands

| Task | Command |
|---|---|
| List routes | `php spark routes` |
| New migration | `php spark make:migration AddFieldToProjects` |
| Run migrations | `php spark migrate --all` |
| Roll back the last batch | `php spark migrate:rollback` |
| Run tests | `vendor/bin/phpunit` |
| Full end-to-end test (fresh MySQL DB `solar_e2e`, ~155 checks) | `tests/e2e/full_flow.sh` |
| Create a user from the command line | `php spark shield:user create -n ravi -e ravi@company.com -g marketing` |
| Send AMC reminders now | `php spark amc:remind` |

## 12. Code map

```
app/Config/AuthGroups.php   roles + default permission matrix (editable in the app)
app/Config/Workflow.php     stages, EB steps, subsidy statuses, lead sources, document titles
app/Config/Solar.php        company, numbering, Tally ledger names, WhatsApp defaults
app/Libraries/Workflow.php  project engine: stages, rules for completing each stage, notifications
app/Libraries/Stock.php     stock ledger, balances, in transit
app/Libraries/Ledger.php    double-entry posting for every document
app/Libraries/TallyXml.php  Tally voucher XML
app/Libraries/Amc.php       AMC contracts, visit schedule, reminders
app/Libraries/WhatsApp.php  WeBase API;  Messenger.php = customer email + WhatsApp per event
app/Controllers/Admin/*     one controller per screen (permissions checked in each)
app/Database/Migrations/*   the full schema
```

## 13. Rules for the team

1. **Every database change is a migration.** No manual ALTER TABLE in phpMyAdmin.
2. **Never commit `.env`**, real passwords or API keys.
3. Check access with `can('permission.name')` / `requirePermission()`, never by role name.
4. Money changes go through `App\Libraries\Ledger` so the accounts and Tally stay correct.
5. Stock changes go through `App\Libraries\Stock::move()`, never direct SQL.
6. Run `vendor/bin/phpunit` before pushing.

## 14. Going live (checklist)

- `CI_ENVIRONMENT = production`, HTTPS, `app.baseURL = 'https://crm.yourdomain.com/'`
- DocumentRoot points to `public/`
- `writable/` writable by the web server (`chown -R www-data writable`). Uploaded
  photos and documents are stored in `writable/uploads` (outside the public folder);
  include it in backups.
- `composer install --no-dev --optimize-autoloader`
- The three cron entries from section 8, plus daily database backups

## 15. Hostinger (shared hosting, no SSH needed)

1. **Files**: deploy the repo into `public_html` (Git deploy or upload). The root
   `.htaccess` sends all requests to `public/` and blocks `app/`, `vendor/`,
   `writable/`, `.git` and `.env`. `vendor/` must exist: run `composer install --no-dev`
   (Hostinger Git deploy / SSH), or upload it.
2. **Database**: hPanel → Databases → create a MySQL database + user. Open
   phpMyAdmin → select the database → **Import** → `docs/database/solar_crm_schema.sql`.
   This creates all tables and the first login `admin@example.com` / `Admin@12345`
   (change it right after the first login).
3. **`.env`**: File Manager → `public_html` → New file `.env` with:

   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'https://yourdomain.com/'
   app.forceGlobalSecureRequests = true
   encryption.key = hex2bin:PUT_64_HEX_CHARS_HERE
   database.default.hostname = localhost
   database.default.database = u000000000_solar
   database.default.username = u000000000_solar
   database.default.password = 'your-db-password'
   database.default.DBDriver = MySQLi
   database.default.port = 3306
   email.fromEmail = 'noreply@yourdomain.com'
   email.fromName = 'Your Company'
   email.protocol = smtp
   email.SMTPHost = 'smtp.hostinger.com'
   email.SMTPUser = 'noreply@yourdomain.com'
   email.SMTPPass = 'mailbox-password'
   email.SMTPPort = 465
   email.SMTPCrypto = ssl
   ```

   For `encryption.key`, generate 64 hex characters (e.g. `php spark key:generate --show`
   on any PC with PHP, or any random hex generator).
4. **Permissions**: `writable/` and its sub-folders must be writable (755 is usually
   fine on Hostinger).
5. **Cron jobs** (hPanel → Advanced → Cron Jobs), using Hostinger's PHP path:
   - every minute: `/usr/bin/php /home/u000000000/public_html/spark queue:work emails --stop-when-empty`
   - every minute: `/usr/bin/php /home/u000000000/public_html/spark queue:work whatsapp --stop-when-empty`
   - daily 08:00: `/usr/bin/php /home/u000000000/public_html/spark amc:remind`
   - every 10 minutes (only if IndiaMART import is on): `/usr/bin/php /home/u000000000/public_html/spark indiamart:pull`
