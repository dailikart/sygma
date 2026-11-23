# Solar CRM: Product Proposal (Draft v0.1)

A CRM and operations system for solar EPC / installer companies. It covers the
whole job: enquiry → site survey → quotation → installation → net-metering and
subsidy → AMC and service.

> **Update:** the confirmed flow below is now built: access control, leads, B2B/B2C
> customers, invoices & payments, the full project workflow (survey → dispatch →
> delivery → installation → EB/PCR → subsidy), store (installation type →
> category → item, kits), purchase (PO → purchase → return), double-entry accounts,
> AMC plans, WhatsApp, reports and Tally export. See [USER_GUIDE.md](USER_GUIDE.md).
> PCR = Project Completion Report, uploaded on the EB portal after installation.
>
> Status: draft for discussion. Section 0 comes from the client call. The rest
> is based on the screenshots of the current CRM and the typical rooftop-solar
> workflow in India. Items marked **[confirm]** need client input.

---

## 0. Confirmed project flow (from the client call)

A project starts when the customer confirms and pays an advance. Each stage can
be assigned to a different person.

| # | Stage | What happens | Who / system action |
|---|---|---|---|
| 1 | **Advance / part payment** | Record one or more part-payments against the quotation (e.g. ₹15,000–20,000). Payments come into the company bank account (no gateway yet). | Accounts records the payment (UTR / cheque no.). **A receipt is emailed and WhatsApped to the customer automatically.** The project is created as *Confirmed*. |
| 2 | **Material dispatch** | Pre-defined **material kits by capacity** (3 kW, 4 kW, 5 kW…) are chosen from the kW in the quotation. | The system makes a **dispatch list** for the inventory person. When the load is taken, stock moves *Warehouse → In transit*. The delivery person marks *Delivered* in the app, stock moves to *At site*, and the customer gets an email. |
| 3 | **Installation** | An installation date is planned. | On site, the installation team uploads: panel photos, the signed **net-meter agreement**, the **inverter serial no.**, and the **panel serial nos. with photos**. The stage can't be completed until all of these are uploaded. |
| 4 | **DCR certificate & portal application** | Upload the DCR (Domestic Content Requirement) certificate for the panels, then submit the application on the PM Surya Ghar portal. | Store the application / reference no. and date. |
| 5 | **EB (DISCOM) process** | Sub-steps: **Acknowledgement → EB charges paid → Sanction → "PCR" documents uploaded → Meter installed & grid connected**. | **EB charges can be paid only after the full quotation amount is settled** (the system blocks the step otherwise). Sanction status is updated from customer feedback. **[confirm the meaning of "PCR". It may be the Project / Plant Completion or Commissioning Report.]** The commissioning date is recorded here. |
| 6 | **Subsidy** | Check on the PM Surya Ghar portal that the customer received the subsidy. | Mark it *Received* (date, amount), then **close the project**. |
| 7 | **Non-subsidy projects** | Same flow without stage 4's portal application and without stage 6. | The project type (*Subsidy* / *Non-subsidy*, *B2C* / *B2B*) decides which stages apply. |

**Final invoice:** generated automatically once the full payment is received.

**AMC:** a **free 5-year AMC starts on the commissioning date** (stage 5). The
system creates the service schedule (e.g. every quarter or every 6 months
**[confirm frequency]**) and sends reminders to the customer and the service
person.

**Other confirmed requirements**
- **Stage-wise assignment and roles.** Each stage has an owner and role-based
  access, like the school ERP modules. One person may hold 2–3 roles.
- **Modules:** B2B and B2C customers, supplier management, stock balance by
  location (warehouse / in transit / site), expenses and accounts, payment
  receipts, final invoice.
- **Notifications:** email and WhatsApp for every key event, plus scheduled
  reports by email (daily / weekly summary).
- **Still to confirm:** the lead → site survey → quotation stage (how they
  work today), and the meaning of "PCR".

### Project stage rules (for development)

```
project.type: subsidy | non_subsidy
project.segment: b2c | b2b

stages (in order)        applies_to          gate (must be true to complete)
1 advance_payment        all                 at least 1 payment recorded
2 material_dispatch      all                 dispatch delivered
3 installation           all                 photos + net-meter agreement + inverter SN + panel SNs uploaded
4 dcr_portal             subsidy             DCR certificate + portal application no.
5 eb_process             all                 ack -> eb_charges (requires balance = 0) -> sanction -> pcr -> meter_connected
6 subsidy                subsidy             subsidy received date + amount
  closed                 all                 final invoice issued; AMC created from commissioning date
```

---

## 1. Why not just reuse the generic CRM?

The current CRM (screenshots) has generic Leads → Customers → Invoices. Solar
companies need things a generic CRM does not have:

| Generic CRM | What a solar business needs |
|---|---|
| Lead = name, company, phone | EB service no., sanctioned load, monthly units/bill, roof type & area, phase, on-grid/off-grid/hybrid, required kW |
| Proposal = free text items | kW-based BOM, generation estimate, savings & payback, subsidy deduction, GST split |
| Project = tasks | Fixed stages: survey → design → material → installation → DISCOM / net-meter → inspection → subsidy → handover |
| Items | Serial-number tracking for panels and inverters (warranty, DCR, subsidy proof) |
| Support tickets | AMC schedules, panel cleaning visits, generation complaints, warranty claims |

---

## 2. Modules

### Phase 1: MVP (sell and track)
1. **Dashboard**: leads by stage, kW sold this month, revenue, pending subsidies,
   installations due, today's follow-ups.
2. **Leads & enquiries**
   - Sources: walk-in, website form, Facebook/Instagram, IndiaMART, JustDial,
     referral, channel partner, Excel import (as in the current CRM).
   - Solar fields: consumer type (residential / commercial / industrial /
     agricultural), EB consumer/service number, DISCOM, sanctioned load, phase,
     avg monthly units & bill, roof type (RCC / sheet / tile / ground), roof area,
     preferred system type, estimated kW, subsidy interest, GPS location.
   - Pipeline (Kanban + list): New → Contacted → Survey Scheduled → Survey Done →
     Quotation Sent → Negotiation → Won / Lost (with reason).
   - Follow-up reminders, call log, assign to sales exec, duplicate check by phone.
3. **Site survey (mobile-friendly)**: assigned engineer, checklist, roof
   dimensions, shading notes, structure height, inverter location, cable run,
   earthing point, photos with GPS and timestamp.
4. **Product catalogue & BOM templates**: panels (brand, Wp, mono/bifacial, DCR
   / non-DCR), inverters, structure, ACDB/DCDB, cables, earthing, LA, meters.
   kW-wise BOM templates (1 kW, 2 kW, 3 kW, 5 kW, 10 kW…) that you can edit per quote.
5. **Quotation / proposal**
   - Auto price from BOM, margin control, discount approval limits.
   - Generation estimate (kW × location yield), savings, payback, 25-year chart.
   - Subsidy calculator (central PM Surya Ghar + state). Rates come from
     settings, not code, because schemes change. **[confirm current rates]**
   - GST split configurable (supply vs. service portion).
   - Branded PDF, versions (Q-001 v1, v2…), send by WhatsApp / email, e-sign
     acceptance link.
6. **Customers & projects**: one click converts a won lead to a customer and a
   project. Project stages with checklist, due dates, responsible person, and a
   timeline that the customer can see.
7. **Documents vault** per customer: Aadhaar, EB bill, property tax receipt,
   bank passbook, photos, agreements, with a "missing documents" alert before
   subsidy filing.
8. **Payments**: milestone schedule (advance / on material / on completion),
   receipts, outstanding report, bank loan tracking (bank, sanction amount,
   disbursal status).
9. **Users, roles, branches**: Admin, Sales, Surveyor, Project Manager,
   Technician, Accounts, Partner. Permissions per module and per action.

### Phase 2: Execute
10. **DISCOM & subsidy tracker**: national portal registration, feasibility
    approval, net-meter application, inspection date, meter installation,
    commissioning report, subsidy claim and receipt. Each step has dates,
    reference numbers, attachments, and an ageing report ("stuck > 15 days").
11. **Inventory & purchase**: vendors, PO, GRN, multi-warehouse stock, material
    issue to a project, serial-number capture (scan) for panels and inverters,
    low-stock alerts.
12. **Installation / field app (PWA)**: technician job list, GPS check-in, step
    photos, material used, customer signature, completion report PDF.
13. **Invoices & accounts**: GST invoices, credit notes, expenses per project,
    project profitability (quoted vs. actual cost), Tally export.
14. **WhatsApp / SMS / email automation**: templates (quote sent, survey
    reminder, installation date, payment due, subsidy credited), using a
    WhatsApp Business API provider and DLT-registered SMS.

### Phase 3: Retain and grow
15. **AMC & service**: warranty register (by serial no.), AMC contracts,
    automatic cleaning or maintenance visit schedule, complaint tickets with SLA.
16. **Customer portal**: project status, documents, invoices, raise a ticket,
    referral link.
17. **Channel partners / dealers**: partner login, submit leads, commission
    rules and payouts.
18. **Generation monitoring (optional)**: pull data from inverter cloud APIs
    (Growatt, Solis, Sungrow, etc.) and alert on low generation.
19. **Reports**: conversion funnel, source ROI, salesperson performance, kW
    installed by month/district, subsidy pending, stock valuation, AMC renewals.
20. **SaaS mode (if selling to other solar companies)**: multi-tenant,
    subscription plans. **[confirm]**

---

## 3. Technology: is CodeIgniter 4 OK?

**Yes, CI4 is a good choice for this team.** You already know CodeIgniter, CI4
is light and fast, and it has everything this product needs. Recommended stack:

| Layer | Choice | Why |
|---|---|---|
| Language | PHP 8.3 | Current supported version, typed properties, enums |
| Framework | CodeIgniter 4.6+ | Team familiarity, small footprint |
| Auth & permissions | CodeIgniter Shield | Official package: login, groups, permissions, API tokens |
| Database | MySQL 8 / MariaDB 10.11 | Use migrations and seeders from day 1 (no manual SQL dumps) |
| UI | Bootstrap 5 + Tabler (or AdminLTE 4) | Clean admin look similar to the current CRM |
| Interactivity | Alpine.js / htmx + jQuery only where needed | Avoid a heavy SPA. Pages stay server-rendered and fast |
| Tables | DataTables, server-side processing | Handles large lead lists (the current CRM's list view) |
| PDF | mPDF or Dompdf | Quotations, invoices, reports |
| Excel | PhpSpreadsheet | Lead import/export |
| Background jobs | `codeigniter4/queue` + cron | WhatsApp/email sending, reminders, reports |
| API | CI4 RESTful controllers + Shield tokens | For the PWA/field app now, and a Flutter app later |
| Files | Local storage now, S3-compatible later | Survey photos, documents |
| Testing | PHPUnit (built into CI4) | At least for calculations: pricing, subsidy, GST |

**Project structure:** modular, so each module is self-contained:

```
app/
  Modules/
    Leads/        (Controllers, Models, Views, Config/Routes.php, Database/Migrations)
    Surveys/
    Quotations/
    Projects/
    Inventory/
    Subsidy/
    Billing/
    Service/
  Libraries/      (SolarCalculator, SubsidyCalculator, PdfService, WhatsAppService)
```

**Alternatives considered:**
- *Build as a module inside the existing Perfex CRM:* fastest way to get
  something running, but Perfex is CI3, its licence limits resale, and you would
  inherit its customisation pain (the same problem as your school software).
  Fine only if this is for internal use and needed in a few weeks.
- *Laravel:* bigger ecosystem (Filament admin panels, queues, packages), but
  the team would have to learn a new framework. It's not worth switching unless
  you plan to hire Laravel developers.

---

## 4. Core database tables (first cut)

```
branches, users (Shield), auth_groups / permissions
lead_sources, lead_statuses, leads, lead_followups, lead_activities
customers, customer_contacts, customer_documents
site_surveys, site_survey_photos
product_categories, products, bom_templates, bom_template_items
quotations, quotation_items, quotation_versions
projects, project_stages, project_stage_logs, project_documents
discom_applications, subsidy_claims
vendors, purchase_orders, po_items, grns, warehouses, stock_ledger, serial_numbers
invoices, invoice_items, payments, payment_schedules, loans, expenses
amc_contracts, service_visits, tickets, ticket_replies
partners, partner_commissions
message_templates, message_logs, settings, activity_log
```

Conventions: `created_by`, `updated_by`, timestamps and soft deletes on every
table; `branch_id` on transactional tables; money stored as `DECIMAL(15,2)`.

---

## 5. Suggested build order

1. Setup: CI4 skeleton, Shield auth, roles, layout, settings, migrations
2. Leads + follow-ups + import/export + Kanban
3. Product catalogue + BOM templates + quotation PDF with calculator
4. Customer conversion + projects/stages + documents + payments
   → **MVP ready for internal use**
5. Site survey PWA, DISCOM/subsidy tracker, inventory & serial numbers
6. Invoices/GST, WhatsApp automation, reports
7. AMC/service, customer portal, partners, monitoring, SaaS

---

## 6. Questions to finalise **[confirm]**

1. What were the key points in the voice note? (Please share a short text
   summary, or the transcript.)
2. Is this for one company (internal use) or a product to sell to many solar
   companies (SaaS / multi-tenant)?
3. Segments: residential rooftop only, or also commercial / industrial / ground
   mount / solar pumps?
4. Which states/DISCOMs first? (Tamil Nadu / TNPDCL?) This affects the
   net-metering steps and the subsidy rules.
5. Do technicians need a separate mobile app, or is a mobile-friendly web app
   (PWA) enough at first?
6. Which WhatsApp API / SMS provider do you already use?
7. Should data be migrated from the existing Perfex CRM (leads, customers)?
8. Accounting: do you need a full ledger inside the system, or invoices plus a
   Tally export?
