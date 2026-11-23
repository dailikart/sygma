# Solar CRM: User Guide

How each team uses the system, in the order work happens. Everyone sees only
the menus their role allows. **My Tasks** always shows what is waiting for you.

## 0. First-time setup (Super Admin)

Do these once, in this order:

1. **Administration → Settings**: company name, address, GSTIN, **state** (decides
   CGST+SGST or IGST), number prefixes, **Email (SMTP)** (Hostinger: `smtp.hostinger.com`,
   port 465, `ssl`, your mailbox address and password; then **Send test**),
   Tally ledger names, WhatsApp keys.
2. **Administration → Users**: create a login for each staff member and tick their
   roles. One person can have several roles (e.g. Store + Delivery).
3. **Administration → Roles & Access**: change which modules each role can use,
   if the defaults don't fit. Super Admin always has full access.
4. **Accounts → Bank / Cash Accounts**: e.g. "HDFC Bank", "Cash" (same names as in Tally).
5. **Accounts → Income Heads / Expense Heads**: e.g. "Fuel Expenses", "Scrap Sale".
6. **Inventory → Locations**: your stores / warehouses.
7. **Inventory → Installation Types** (On-grid, Off-grid, Hybrid…) →
   **Item Categories** (Solar Panel, Inverter, Structure…) → **Items** (with HSN,
   GST %, purchase and sale price, minimum stock).
8. **Inventory → Item Groups / Kits**: one per system size, e.g. "3 kW On-grid kit"
   = 6 panels + 1 inverter + 1 structure set…
9. **Inventory → Stock Adjustment**: enter opening stock (type "Opening stock").
10. **AMC / Service → AMC Plans**: e.g. "Free 5-year AMC", 60 months, a visit
    every 6 months. Tick "Default".
11. **Purchase → Suppliers**.
12. (Optional) **Administration → WhatsApp Templates**: map each event to your
    approved WeBase template.

## 1. Marketing: leads to customer

1. **Marketing → Leads → New Lead**: name, phone, city, source, B2B/B2C, kW needed,
   next follow-up date. A warning appears if the phone number already exists.
2. Open the lead → **Add follow-up** after every call (what was discussed, new
   status, next date). "Follow-ups due" on the dashboard lists today's calls.
3. When the customer confirms → **Convert to customer** (choose **B2B** or **B2C**).
   Check the customer details (address, state, GSTIN for B2B, EB service no.).

## 2. Invoice: starts the project

1. Customer page → **Create Invoice**.
2. Choose **Subsidy customer** or **Non-subsidy customer**, installation type and kW.
3. Add lines: pick items, or **Load items** from a kit, or type one line such as
   "3 kW On-grid Solar System – supply & installation".
4. Tick **"Email this invoice (PDF) to the customer"** only if you want it emailed now
   (you can also email it later from the invoice page).
5. Keep **"Start the installation project"** ticked → the project is created and
   the **site surveyor is notified**.
6. **Upload customer documents** on the invoice or project page: click
   **+ Add document**, type a title (Aadhaar card, EB bill, property tax…), choose
   the file, and repeat for each document. Each file is saved under its title.
   These are needed later for the **PCR**.

## 3. Accounts: payments

- Invoice → **Payment**: amount, date, mode (bank / UPI / cheque / cash), the account it
  came into, UTR / transaction ID. Tick **"Email the payment receipt (PDF) to the
  customer"** to send the receipt (and WhatsApp, if set up). Nothing is sent if it is not ticked.
  The screen tells you whether the email went through.
- Part payments are allowed. The invoice shows **Partially Paid** until fully paid.
- When fully paid, the EB team is told that **EB charges can now be paid**.

## 4. Site Surveyor

**My Tasks** → open the project → **Site Survey** card:
upload site / roof photos, fill roof type, area, recommended kW →
**Approve site** (the store is notified) or **Reject** with a reason (the project goes
on hold and the admin is told; it can be reopened later).

## 5. Store In-charge: dispatch

1. Project → **Prepare dispatch list from kit**. The kit for the project's kW is
   loaded. Change quantities or add items if needed. Each line shows the stock
   in the chosen store and warns **Out of Stock**.
2. **Print dispatch list**, load the vehicle.
3. **Dispatch now** (vehicle no., driver). Stock is reduced and shows as
   **In transit**. The delivery person is notified and the customer gets a message.

## 6. Delivery Person (works on a phone)

**My Tasks** or **Dispatch** → open the dispatch → **take a photo** of the material at
the site → **Mark delivered**. The installation team is notified and the customer gets
a message.

## 7. Installation Team (works on a phone)

Project → **Installation** card. Upload:
**panel photo**, **net-meter agreement**, **panel serial number photo** (and an inverter photo if you want).
Enter the inverter serial no. and the panel serial nos. (one per line) →
**Installation completed**. The system refuses to complete until everything is uploaded.

## 8. EB / Subsidy Liaison

**EB / Net Meter** card: tick each step with its date:
Acknowledgement / feasibility → **EB charges paid** (locked until the invoice is fully
paid) → Sanction → **PCR uploaded** (needs the customer documents) → EB inspection →
**Net meter installed & commissioned**. Upload PCR copy, sanction letter, EB receipt.
Choose the AMC plan → **Commissioned – complete EB process**.

- The **commissioning date starts the AMC** and all service visits are scheduled.

**Subsidy** card (subsidy customers only): application no., status
(Applied → Under verification → Sanctioned → **Received by customer** / Rejected /
Refunded), amount, date → **Close project**.

## 9. Service / AMC

**AMC / Service → Service Visits** lists visits due in the next 30 days. Open the
contract → assign a technician → after the visit, enter what was done, a photo,
**Done**. Customers and technicians are reminded automatically
(`php spark amc:remind`, run daily by cron).

## 10. Purchase & stock

- **Purchase Orders** → **Receive goods** creates the Purchase with the
  remaining quantities. Stock goes into the chosen store and the supplier's balance goes up.
- **Purchases** can also be entered directly (supplier bill).
- **Purchase Returns** (from a purchase → "Return items"): stock goes out and a
  debit note is posted.
- **Stock Balance**: stock per store, in transit, and value. Low stock is flagged on the dashboard.
- **Stock Adjustment**: opening stock and physical-count corrections.

## 11. Accounts

- **Income** (other income), **Expenses** (attach the bill; link to a project for
  project cost), **Supplier Payments**.
- Every invoice, receipt, purchase, return, payment, income and expense is posted
  automatically as a double-entry voucher.
- **Journal / Contra**: manual entries (e.g. cash deposited to bank).
- **Day Book**, **Ledgers** (any customer, supplier, bank or expense head
  statement), **Trial Balance**.
- Opening balances: enter bank/cash openings in the bank account master, supplier
  openings in the supplier master, and the balancing figure as a **Capital Account**
  ledger (Ledgers page) so the trial balance matches.

## 11a. Voucher numbers and printing

Every accounting entry gets an automatic reference number, shown on the form
before you save: invoices `INV/2026-27/0001`, receipts `RCT/…`, income `INC/…`,
expenses `EXP/…`, supplier payments `SPAY/…`, purchases `PUR/…`, returns `PRN/…`,
journal / contra `JV/…` (the prefixes can be changed in Settings → Numbering).

Click the voucher number (or 🖨) on any list, the Day Book or a ledger to print it, Tally style:
- **Receipt** (customer payments, other income): received with thanks from, the sum in
  words, by bank/cash with mode and reference, against which bill, remarks, amount,
  "Subject to Realisation" for cheques, authorised signatory.
- **Payment Voucher** (expenses, supplier payments): paid to, the sum, by, being, receiver's
  and authorised signatures.
- **Sales / Purchase / Debit Note / Journal / Contra**: Dr / Cr voucher with narration.

The receipt emailed to the customer is the same formal receipt.

## 12. User log (who did what)

**Administration → User Log** (admins): every saved entry, change, delete, login,
logout and failed login, with the user, date & time and IP address. Filter by
user, date range or text (e.g. an invoice number) and export to Excel. Lists of
invoices, payments, income, expenses and supplier payments also show **By** (who
entered it). Customer emails and WhatsApp results are in **Administration → Message Log**.

## 13. Reports

| Report | Shows |
|---|---|
| Income vs Expense | Month by month: sales, purchases, receipts, other income, expenses, supplier payments, net |
| Inventory Usage (B2B/B2C) | Items used by B2B and B2C customers in a period, with value |
| Supplier Summary | Opening, purchases, returns, paid, balance payable, open POs; statement per supplier |
| Project Status | Active projects, current stage, who is assigned, days in stage |
| Subsidy Status | Subsidy projects with application no., status, amount, received date |

Every report has **Excel** and **Print** buttons.

## 14. Tally export

**Reports → Tally Export**: choose the period (and voucher type if you want).

1. First time only: **Ledger masters XML** → in Tally: *Gateway of Tally → Import →
   Masters*.
2. **Vouchers XML** → in Tally: *Gateway of Tally → Import → Transactions*.

Voucher types exported: Sales, Receipt, Purchase, Debit Note, Payment, Journal, Contra.
GST ledger names follow **Settings → Tally ledger names** (e.g. "Output CGST @ 6%").

## 15. Latest additions

- **Database updates (Administration → Database Updates):** after deploying a new version, the Super Admin
  sees a yellow banner when the database needs updating; click **Run database updates** (take a phpMyAdmin
  export first). No SSH needed.
- **Appearance (Settings):** choose a theme colour, upload the company **logo** and the **authorised
  signature** – printed on invoices, receipts and vouchers.
- **Customer documents:** a fixed checklist from **Administration → Document Types** (mark which are
  required), each with **View** / **Download**; extra files via "Add other document". The PCR step needs
  every *required* document.
- **Payment mode – Cash / Bank loan:** set on the invoice (or later on the invoice / project page). It can
  change only until material is dispatched, and **material cannot be dispatched until it is set**.
  For a loan: bank, loan amount, sanction ref; 3 disbursements are created – edit amounts/dates or
  **+ Add disbursement**. When the bank pays, record **Payment → mode "Loan disbursement from bank"** and pick
  the disbursement; the receipt says "LOAN DISBURSEMENT from <bank>".
- **Invoice format:** *Cumulative* (one line for the solar system + GST summary, default for B2C) or
  *Item-wise* (default for B2B). Changeable on the invoice.
- **Dispatch:** delete a **draft** dispatch; record **serial numbers** (inverter S.No etc.) per line – the
  installation form is filled from them.
- **DCR & Installation Submission** (subsidy projects, after installation): upload the **DCR certificate**
  and enter the portal submission reference to close the step.
- **EB / Net Meter** can be completed only when the invoice is **fully paid**.
- **Reports:** *Customers: Cash / Loan* (loan amount, disbursed, pending, due), **Profit & Loss** (Accounts
  and Reports menus), Excel export on Income, Expenses and Supplier Payments, payment-mode filters on
  Invoices and Payments.
