# Modules & options per company

Every company runs the same code. What each company **has** is switched on or off
per company in two levels:

| Level | Example | Effect when off |
|---|---|---|
| **Module** | 3D roof design, Quotations, Projects, Inventory, Purchase, Accounts, Tally, AMC & Service, WhatsApp | Menu entries, dashboard boxes, Settings sections and Roles & Access permissions disappear; its pages (and public links / webhooks) cannot be opened |
| **Option** inside a module | Bank-loan tracking and GST e-invoice (Invoices), EB stage / subsidy stages / customer status page (Projects), IndiaMART / Facebook / Google lead import (Leads) | Just that feature disappears; new projects skip switched-off stages; forms cannot submit a switched-off choice |

**Data is never deleted.** Switching something back on brings it all back.

Core modules (Leads & Customers, Invoices & Payments) are always on; their options can be off.

## Dependencies

| Needs | Module / option |
|---|---|
| Inventory | Projects, Purchase |
| Accounts | Tally export |
| EB / net-meter stage | PM Surya Ghar subsidy |

If something a module needs is switched off, that module is unavailable too (shown with an
"off: … is off" label) and comes back automatically when the needed module is switched on again.

## Who changes it

Only **you** (the software owner), never the customer:

- On installs where `.env` has `features.manage = true`, the Super Admin sees
  **Administration → Modules & Options**. Leave it out on customer servers.
- From the command line (any install):

  ```bash
  php spark features
  ```

  ```bash
  php spark features off amc invoices.loan projects.subsidy
  ```

  ```bash
  php spark features on amc
  ```

The choice is stored in each company's own database (`settings` table, key `Features.off`).
In multi-company mode ([SAAS.md](SAAS.md)) the owner control panel sets the modules of each company; the screen and command above are not used there.

## Adding a new module or option

1. Add it to `app/Config/Features.php` (title, permissions prefix, URL prefixes, Settings sections, requires).
2. For an option that changes behaviour inside a screen, check it with
   `\App\Libraries\Features::on('module.option')` where it applies.
3. Nothing else: menu, permissions, Settings and URL blocking follow the catalogue.
