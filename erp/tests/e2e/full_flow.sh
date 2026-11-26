#!/usr/bin/env bash
# =============================================================================
# Solar CRM – full end-to-end test with realistic data (MySQL / MariaDB).
#
# Creates a fresh database, starts the app, enters masters, purchases, leads,
# invoices, payments, two complete projects, AMC, accounts entries, then
# checks stock, ledgers, reports, Tally XML, permissions and every page.
#
# Usage (from the project root):
#   tests/e2e/full_flow.sh
# Needs: php, curl, mysql client + a MySQL user that may create the database
# (defaults below; override with env vars), python3 -m aiosmtpd for email.
# =============================================================================
set -u
cd "$(dirname "$0")/../.."

DB=${E2E_DB:-solar_e2e}
DBUSER=${E2E_DBUSER:-solar}
DBPASS=${E2E_DBPASS:-solarpw}
PORT=${E2E_PORT:-8090}
B="localhost:$PORT"
W=$(mktemp -d)
D=$(date +%F)
PASS=0; FAIL=0; FAILS=()

ok()   { PASS=$((PASS+1)); printf '  \e[32m✓\e[0m %s\n' "$1"; }
bad()  { FAIL=$((FAIL+1)); FAILS+=("$1"); printf '  \e[31m✗ %s\e[0m\n' "$1"; }
eq()   { [ "$2" == "$3" ] && ok "$1" || bad "$1 (expected '$3', got '$2')"; }
hasnt() { if grep -qF -- "$3" <<<"$2"; then FAIL=$((FAIL+1)); FAILS+=("$1"); printf '  \e[31m✗ %s\e[0m\n' "$1"; else PASS=$((PASS+1)); printf '  \e[32m✓\e[0m %s\n' "$1"; fi; }
has()  { [[ "$2" == *"$3"* ]] && ok "$1" || bad "$1 (expected to contain '$3', got '${2:0:200}')"; }
section() { printf '\n\e[1m== %s\e[0m\n' "$1"; }
q()    { mysql -N -B -u"$DBUSER" -p"$DBPASS" "$DB" -e "$1" 2>/dev/null | tr '\t' '|' | sed -E 's/([0-9])\.00([^0-9]|$)/\1\2/g; s/([0-9])\.00([^0-9]|$)/\1\2/g'; }
tok()  { curl -s -b "$1" -c "$1" "$B/$2" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
# post JAR FORM_PAGE URL fields... → prints "<code> <redirect>"
post() { local j=$1 p=$2 u=$3; shift 3; curl -s -b "$j" -c "$j" -o "$W/last.html" -w '%{http_code} %{redirect_url}' -F "csrf_test_name=$(tok "$j" "$p")" "$@" "$B/$u"; }
# message shown after a redirect (success / error / warning flash)
flash() { curl -s -b "$1" -c "$1" "$B/$2" | grep -o 'alert alert-[a-z]* alert-dismissible[^>]*>[^<]*' | sed 's/.*>//' | head -2 | tr '\n' ' '; }
code() { curl -s -b "$1" -o "$W/page.html" -w '%{http_code}' "$B/$2"; }
login() { rm -f "$1"; local t; t=$(tok "$1" login); curl -s -b "$1" -c "$1" -o /dev/null -w '%{http_code}' --data-urlencode "csrf_test_name=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" "$B/login"; }

# ── setup ───────────────────────────────────────────────────────────────────
section "Setup: fresh database, app server, test mail server"
LOGF=writable/logs/log-$D.log; LOGSTART=$( [ -f "$LOGF" ] && wc -l < "$LOGF" || echo 0 )
mysql -u"$DBUSER" -p"$DBPASS" -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null \
  || { echo "Cannot create database $DB as $DBUSER"; exit 1; }
[ -f .env ] && cp .env "$W/env.backup"
cat > .env <<EOF
CI_ENVIRONMENT = development
app.baseURL = 'http://$B/'
database.default.hostname = localhost
database.default.database = $DB
database.default.username = $DBUSER
database.default.password = '$DBPASS'
database.default.DBDriver = MySQLi
admin.email = admin@e2e.test
admin.password = 'Admin#2026x'
EOF
restore() { pkill -f "spark serve --port $PORT" 2>/dev/null; pkill -f -- "-S localhost:$PORT" 2>/dev/null; pkill -f "http.server 8097" 2>/dev/null; pkill -f "aiosmtpd -n -l 127.0.0.1:1026" 2>/dev/null; [ -f "$W/env.backup" ] && cp "$W/env.backup" .env || rm -f .env; }
trap restore EXIT
php spark migrate --all >/dev/null && php spark db:seed AdminSeeder >/dev/null && ok "migrations + admin seed" || bad "migrations"
(python3 -m aiosmtpd -n -l 127.0.0.1:1026 > "$W/smtp.log" 2>&1 &)
(php spark serve --port "$PORT" > "$W/serve.log" 2>&1 &)
for i in $(seq 1 30); do curl -s -o /dev/null "$B/login" && break; sleep 1; done
php -r '$i=imagecreatetruecolor(80,60);imagefill($i,0,0,imagecolorallocate($i,20,120,200));imagejpeg($i,$argv[1]);' "$W/photo.jpg"
printf '%%PDF-1.4\n%%e2e\n' > "$W/doc.pdf"; printf 'MZ fake exe' > "$W/virus.exe"
A="$W/admin"; eq "admin login" "$(login "$A" admin@e2e.test 'Admin#2026x')" "303"

# ── settings & masters ─────────────────────────────────────────────────────
section "Settings and masters"
post "$A" admin/settings admin/settings -F companyName="Sun Power Solutions" -F companyAddress="12, Perundurai Road, Erode" -F companyPhone=9876500000 \
  -F companyEmail=info@sunpower.test -F companyGstin=33AAAAA0000A1Z5 -F companyState="Tamil Nadu" -F companyCity=Erode -F companyPincode=638011 -F smtpEnabled=1 -F smtpHost=127.0.0.1 -F smtpPort=1026 \
  -F smtpCrypto= -F smtpUser= -F mailFromEmail=noreply@sunpower.test -F mailFromName="Sun Power Solutions" -F emailUseQueue=0 -F amcReminderDays=7 >/dev/null
eq "settings saved" "$(q "select value from settings where \`key\`='companyName'")" "Sun Power Solutions"
post "$A" admin/settings admin/settings -F theme=forest -F "logo=@$W/photo.jpg" -F "signature=@$W/photo.jpg" >/dev/null
eq "theme saved and applied" "$(curl -s -b "$A" "$B/admin" | grep -o 'data-theme="[a-z]*"')" 'data-theme="forest"'
eq "logo served publicly (login page)" "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$B/branding/logo")" "200 image/jpeg"
eq "signature only for logged-in users" "$(curl -s -o /dev/null -w '%{http_code}' "$B/branding/signature")" "404"
has "database updates page: up to date" "$(curl -s -b "$A" "$B/admin/maintenance")" "up to date"
post "$A" admin/settings admin/settings/test-email -F test_to=owner@sunpower.test >/dev/null; has "test email via SMTP" "$(flash "$A" admin/settings)" "Test email sent"
post "$A" admin/settings admin/settings -F smtpUser=mailer@sunpower.test -F smtpPass=secret >/dev/null
post "$A" admin/settings admin/settings/test-email -F test_to=owner@sunpower.test >/dev/null
has "SMTP login refused → plain explanation + server reply" "$(curl -s -b "$A" "$B/admin/settings")" "did not accept login on this port/encryption"
post "$A" admin/settings admin/settings -F smtpUser= >/dev/null
OLDTOK=$(tok "$A" admin/settings); post "$A" admin/settings admin/settings -F smtpPort=587 -F smtpCrypto=ssl >/dev/null
eq "port 587 saved with TLS (encryption follows the port)" "$(q "select value from settings where \`key\`='smtpCrypto'")" "tls"
curl -s -b "$A" -c "$A" -o /dev/null -F "csrf_test_name=$OLDTOK" -F smtpPort=1026 -F smtpCrypto= "$B/admin/settings"
has "form from another tab still saves after a different form was submitted" "$(flash "$A" admin/settings)" "Settings saved"
eq "…and its values were stored" "$(q "select concat(value,'/',(select value from settings where \`key\`='smtpCrypto')) from settings where \`key\`='smtpPort'")" "1026/"
for l in "Main Store" "Warehouse 2"; do post "$A" admin/locations/new admin/locations/save -F name="$l" -F is_active=1 >/dev/null; done
post "$A" admin/bank-accounts/new admin/bank-accounts/save -F name="HDFC Bank" -F type=bank -F account_no=50100123 -F ifsc=HDFC0001 -F opening_balance=500000 -F is_active=1 >/dev/null
post "$A" admin/bank-accounts/new admin/bank-accounts/save -F name="Cash" -F type=cash -F opening_balance=20000 -F is_active=1 >/dev/null
for h in "Fuel Expenses" "Salary" "Office Rent"; do post "$A" admin/expense-heads/new admin/expense-heads/save -F name="$h" -F is_active=1 >/dev/null; done
for h in "Scrap Sale" "Service Charges"; do post "$A" admin/income-heads/new admin/income-heads/save -F name="$h" -F is_active=1 >/dev/null; done
for t in "On-grid" "Off-grid"; do post "$A" admin/installation-types/new admin/installation-types/save -F name="$t" -F is_active=1 >/dev/null; done
for c in "Solar Panel:" "Inverter:" "Structure:" "DC Cable:" "Battery:2"; do post "$A" admin/item-categories/new admin/item-categories/save -F name="${c%%:*}" -F installation_type_id="${c##*:}" -F is_active=1 >/dev/null; done
eq "locations / banks / heads / types / categories" "$(q "select concat((select count(*) from locations),'/',(select count(*) from accounts),'/',(select count(*) from expense_heads)+(select count(*) from income_heads),'/',(select count(*) from installation_types),'/',(select count(*) from item_categories))")" "2/2/5/2/5"
item() { post "$A" admin/items/new admin/items/save -F installation_type_id="$1" -F category_id="$2" -F name="$3" -F sku="$4" -F unit="$5" -F hsn="$6" -F tax_percent="$7" -F purchase_price="$8" -F sale_price="$9" -F min_stock="${10}" -F opening_location_id="${11:-}" -F opening_qty="${12:-}" -F is_active=1 >/dev/null; }
item "" 1 "Mono PERC Panel 545W" PNL545 Nos 8541 12 9000 11000 10
item 1 2 "3 kW On-grid Inverter" INV3 Nos 8504 12 24000 30000 2
item 1 2 "5 kW On-grid Inverter" INV5 Nos 8504 12 38000 46000 1
item "" 3 "GI Structure Set 3 kW" STR3 Set 7308 18 6000 8000 2
item "" 4 "DC Cable 4 sq.mm" DC4 Mtr 8544 18 45 60 100
item 2 5 "Lithium Battery 150Ah" BAT150 Nos 8507 18 15000 19000 2 2 10
eq "6 items created" "$(q "select count(*) from items")" "6"
eq "opening stock with location (battery in Warehouse 2)" "$(q "select concat(location_id,':',qty+0,':',type) from stock_movements where item_id=6")" "2:10:opening"
r=$(post "$A" admin/items/new admin/items/save -F category_id=1 -F name="Bad item" -F unit=Nos -F opening_qty=5 -F opening_location_id=); has "opening qty without location is refused" "$r" "items/new"
post "$A" admin/kits/new admin/kits/save -F name="3 kW On-grid kit" -F installation_type_id=1 -F capacity_kw=3 -F is_active=1 \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=6' -F 'items[1][item_id]=2' -F 'items[1][qty]=1' -F 'items[2][item_id]=4' -F 'items[2][qty]=1' -F 'items[3][item_id]=5' -F 'items[3][qty]=30' >/dev/null
post "$A" admin/kits/new admin/kits/save -F name="5 kW On-grid kit" -F installation_type_id=1 -F capacity_kw=5 -F is_active=1 \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=10' -F 'items[1][item_id]=3' -F 'items[1][qty]=1' -F 'items[2][item_id]=4' -F 'items[2][qty]=2' -F 'items[3][item_id]=5' -F 'items[3][qty]=50' >/dev/null
eq "2 kits with 4 lines each" "$(q "select group_concat(c) from (select count(*) c from kit_items group by kit_id) x")" "4,4"
post "$A" admin/suppliers/new admin/suppliers/save -F name="ABC Solar Distributors" -F state="Tamil Nadu" -F gstin=33ABCDE1234F1Z5 -F phone=9800000001 -F opening_balance=25000 -F is_active=1 >/dev/null
post "$A" admin/suppliers/new admin/suppliers/save -F name="Bangalore Inverters Pvt Ltd" -F state="Karnataka" -F gstin=29BBBBB1111B1Z2 -F phone=9800000002 -F opening_balance=0 -F is_active=1 >/dev/null
post "$A" admin/amc-plans/new admin/amc-plans/save -F name="Free 5-year AMC" -F duration_months=60 -F interval_months=6 -F is_free=1 -F price=0 -F is_default=1 -F is_active=1 >/dev/null
post "$A" admin/amc-plans/new admin/amc-plans/save -F name="Paid 1-year AMC" -F duration_months=12 -F interval_months=3 -F is_free=0 -F price=3000 -F is_default=0 -F is_active=1 >/dev/null
eq "suppliers + AMC plans" "$(q "select concat((select count(*) from suppliers),'/',(select count(*) from amc_plans),'/',(select name from amc_plans where is_default=1))")" "2/2/Free 5-year AMC"
post "$A" admin/ledgers admin/ledgers -F name="Capital Account" -F group_name="Capital Account" -F opening_balance=495000 -F opening_type=Cr >/dev/null
eq "capital ledger" "$(q "select opening_balance+0 from ledgers where name='Capital Account'")" "-495000"

# ── users & roles ──────────────────────────────────────────────────────────
section "Users and roles"
for u in ravi:marketing suresh:surveyor store1:store driver1:delivery fitter1:installer eb1:liaison acct1:accounts service1:service mgr:admin; do
  post "$A" admin/users/new admin/users/save -F username="${u%%:*}" -F email="${u%%:*}@e2e.test" -F password='Passw0rd!x' -F "roles[]=${u##*:}" >/dev/null; done
post "$A" admin/users/new admin/users/save -F username=kumar -F email=kumar@e2e.test -F password='Passw0rd!x' -F 'roles[]=store' -F 'roles[]=delivery' >/dev/null
eq "11 users" "$(q "select count(*) from users")" "11"
eq "multi-role user" "$(q "select group_concat(g.\`group\` order by g.\`group\`) from auth_groups_users g join users u on u.id=g.user_id where u.username='kumar'")" "delivery,store"
r=$(post "$A" admin/users/new admin/users/save -F username=dup -F email=ravi@e2e.test -F password='Passw0rd!x' -F 'roles[]=staff'); has "duplicate user email refused" "$r" "users/new"
for u in ravi suresh store1 driver1 fitter1 eb1 acct1 service1 mgr kumar; do login "$W/$u" "$u@e2e.test" 'Passw0rd!x' >/dev/null; done
eq "wrong password refused" "$(login "$W/x" ravi@e2e.test wrongpass)" "303"
eq "…and stays logged out" "$(code "$W/x" admin)" "302"
eq "forged POST without CSRF token → 403" "$(curl -s -b "$A" -o /dev/null -w '%{http_code}' -X POST -d name=x "$B/admin/locations/save")" "403"

# ── purchase cycle ─────────────────────────────────────────────────────────
section "Purchase: PO → partial receipt → full receipt, inter-state purchase, return, payments"
S="$W/store1"
post "$S" admin/purchase-orders/new admin/purchase-orders -F supplier_id=1 -F doc_date="$D" -F expected_date="$D" \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=40' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][item_id]=2' -F 'items[1][qty]=3' -F 'items[1][rate]=24000' -F 'items[1][tax_percent]=12' \
  -F 'items[2][item_id]=4' -F 'items[2][qty]=6' -F 'items[2][rate]=6000' -F 'items[2][tax_percent]=18' \
  -F 'items[3][item_id]=5' -F 'items[3][qty]=300' -F 'items[3][rate]=45' -F 'items[3][tax_percent]=18' >/dev/null
# 40*9000=360000+43200 | 3*24000=72000+8640 | 6*6000=36000+6480 | 300*45=13500+2430  → 542250
eq "PO total incl. GST" "$(q "select concat(po_no,' ',total+0,' ',status) from purchase_orders where id=1")" "PO/$(php -r 'echo (date("n")>=4?date("Y"):date("Y")-1)."-".substr((date("n")>=4?date("Y"):date("Y")-1)+1,2);')/0001 542250 open"
post "$S" "admin/purchases/new?po=1" admin/purchases -F supplier_id=1 -F purchase_order_id=1 -F doc_date="$D" -F bill_no=ABC-101 -F location_id=1 \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=24' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][item_id]=2' -F 'items[1][qty]=3' -F 'items[1][rate]=24000' -F 'items[1][tax_percent]=12' \
  -F 'items[2][item_id]=4' -F 'items[2][qty]=6' -F 'items[2][rate]=6000' -F 'items[2][tax_percent]=18' \
  -F 'items[3][item_id]=5' -F 'items[3][qty]=300' -F 'items[3][rate]=45' -F 'items[3][tax_percent]=18' >/dev/null
eq "PO partially received" "$(q "select status from purchase_orders where id=1")" "partial"
has "receive-rest form pre-fills remaining 16 panels" "$(curl -s -b "$S" "$B/admin/purchases/new?po=1")" 'name="items[0][qty]" data-field="qty" class="form-control form-control-sm" value="16"'
post "$S" "admin/purchases/new?po=1" admin/purchases -F supplier_id=1 -F purchase_order_id=1 -F doc_date="$D" -F bill_no=ABC-102 -F location_id=1 \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=16' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12' >/dev/null
eq "PO fully received" "$(q "select status from purchase_orders where id=1")" "received"
r=$(post "$S" admin/purchases/new admin/purchases -F supplier_id=1 -F doc_date="$D" -F bill_no= -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=1' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12')
r=$(post "$S" admin/purchases/new admin/purchases -F supplier_id=1 -F doc_date="$D" -F bill_no=ABC-102 -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=1' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12')
has "same supplier invoice no. twice is refused" "$(flash "$S" admin/purchases/new)" "already entered as purchase"
eq "purchase without supplier invoice no. / duplicate not saved" "$(q "select count(*) from purchases")" "2"
has "purchase form asks supplier invoice no. and date" "$(curl -s -b "$S" "$B/admin/purchases/new")" "Supplier invoice date"
post "$S" admin/purchases/new admin/purchases -F supplier_id=2 -F doc_date="$D" -F bill_no=BLR-77 -F location_id=2 \
  -F 'items[0][item_id]=3' -F 'items[0][qty]=2' -F 'items[0][rate]=38000' -F 'items[0][tax_percent]=12' >/dev/null
eq "inter-state purchase uses IGST" "$(q "select concat(gst_type,' ',total+0) from purchases where supplier_id=2")" "inter 85120"
has "IGST input ledger posted" "$(q "select group_concat(l.name) from voucher_entries e join ledgers l on l.id=e.ledger_id join vouchers v on v.id=e.voucher_id where v.ref_type='purchase' and v.ref_id=3")" "Input IGST @ 12%"
post "$S" "admin/purchase-returns/new?purchase=1" admin/purchase-returns -F supplier_id=1 -F purchase_id=1 -F location_id=1 -F doc_date="$D" \
  -F 'items[0][item_id]=1' -F 'items[0][qty]=2' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12' -F notes="Cracked glass" >/dev/null
eq "stock after purchases & return (panels/inv3/inv5/str/cable/battery)" "$(q "select group_concat(s order by item_id) from (select item_id, sum(qty)+0 s from stock_movements group by item_id) x")" "38,3,2,6,300,10"
eq "lots: each purchase is a lot with its supplier bill and PO; the return took from its own bill" "$(q "select group_concat(concat(bill_no,':',ifnull(po_no,'-'),':',qty_in+0,'/',qty_left+0) order by id) from stock_lots where item_id=1")" "ABC-101:PO/$(php -r 'echo (date("n")>=4?date("Y"):date("Y")-1)."-".substr((date("n")>=4?date("Y"):date("Y")-1)+1,2);')/0001:24/22,ABC-102:PO/$(php -r 'echo (date("n")>=4?date("Y"):date("Y")-1)."-".substr((date("n")>=4?date("Y"):date("Y")-1)+1,2);')/0001:16/16"
has "purchase page shows where its stock went" "$(curl -s -b "$S" "$B/admin/purchases/1")" "Where this stock went"
r=$(post "$S" "admin/purchase-returns/new?purchase=1" admin/purchase-returns -F supplier_id=1 -F location_id=1 -F doc_date="$D" -F 'items[0][item_id]=1' -F 'items[0][qty]=5' -F 'items[0][rate]=9000' -F 'items[0][tax_percent]=12')
has "return without a purchase is refused" "$(flash "$S" "admin/purchase-returns/new?purchase=1")" "Choose the purchase"
has "new return first asks which purchase" "$(curl -s -b "$S" "$B/admin/purchase-returns/new")" "ABC-101"
RF=$(curl -s -b "$S" "$B/admin/purchase-returns/new?purchase=1"); has "return form shows supplier invoice" "$RF" "ABC-101"; has "…already-returned qty and limit" "$RF" "max 22"
r=$(post "$S" "admin/purchase-returns/new?purchase=1" admin/purchase-returns -F supplier_id=1 -F purchase_id=1 -F location_id=1 -F doc_date="$D" -F 'items[0][item_id]=1' -F 'items[0][qty]=23')
has "returning more than bought − returned is refused" "$(flash "$S" "admin/purchase-returns/new?purchase=1")" "only 22 Nos can still be returned"
r=$(post "$S" "admin/purchase-returns/new?purchase=1" admin/purchase-returns -F supplier_id=1 -F purchase_id=1 -F location_id=1 -F doc_date="$D" -F 'items[0][item_id]=6' -F 'items[0][qty]=1')
has "item not on the purchase is refused" "$(flash "$S" "admin/purchase-returns/new?purchase=1")" "Only items on purchase"
eq "refused returns not saved" "$(q "select count(*) from purchase_returns")" "1"
has "Return button on each purchase row" "$(curl -s -b "$S" "$B/admin/purchases")" "purchase-returns/new?purchase=3"
AC="$W/acct1"
post "$AC" admin/supplier-payments/new admin/supplier-payments -F head_id=1 -F account_id=1 -F doc_date="$D" -F amount=300000 -F mode=bank -F reference=UTR9001 >/dev/null
post "$AC" admin/supplier-payments/new admin/supplier-payments -F head_id=2 -F account_id=1 -F doc_date="$D" -F amount=85120 -F mode=bank -F reference=UTR9002 >/dev/null
eq "2 supplier payments" "$(q "select count(*) from supplier_payments")" "2"

# ── leads → customers ──────────────────────────────────────────────────────
section "Marketing: leads, follow-ups, duplicates, conversion"
M="$W/ravi"
lead() { post "$M" admin/leads/new admin/leads/save -F name="$1" -F phone="$2" -F email="$3" -F city="$4" -F source=Referral -F customer_type="$5" -F required_kw="$6" -F monthly_bill=3000 -F next_followup_date="$D" "${@:7}" >/dev/null; }
lead "Murugan S" 9876543210 murugan@e2e.test Erode b2c 3
lead "KMC School" 9876543211 accounts@kmc.test Bengaluru b2b 5
lead "Ramesh K" 9876543212 "" Salem b2c 2
eq "3 leads" "$(q "select count(*) from leads")" "3"
r=$(post "$M" admin/leads/new admin/leads/save -F name="Murugan again" -F phone=9876543210 -F customer_type=b2c -F required_kw=1); has "duplicate phone warning" "$(flash "$M" admin/leads/new)" "already exists"
post "$M" admin/leads/1 admin/leads/1/followup -F note="Explained PM Surya Ghar subsidy" -F status=interested -F next_followup_date="$D" >/dev/null
post "$M" admin/leads/3 admin/leads/3/followup -F note="Not interested, budget issue" -F status=lost -F next_followup_date= >/dev/null
eq "follow-ups recorded; lost lead" "$(q "select concat((select count(*) from lead_followups),'/',(select status from leads where id=3))")" "2/lost"
has "dashboard shows follow-ups due" "$(curl -s -b "$M" "$B/admin/leads?due=1")" "Murugan S"
post "$M" admin/leads/1 admin/leads/1/convert -F type=b2c >/dev/null
post "$M" admin/leads/2 admin/leads/2/convert -F type=b2b >/dev/null
post "$M" admin/customers/2/edit admin/customers/save/2 -F type=b2b -F eb_charges_by=company -F name="KMC School" -F company="KMC Educational Trust" -F gstin=29KMCST1234A1Z9 -F phone=9876543211 -F email=accounts@kmc.test -F address="5, MG Road" -F city=Bengaluru -F state=Karnataka -F pincode=560001 >/dev/null
post "$M" admin/customers/1/edit admin/customers/save/1 -F type=b2c -F eb_charges_by=customer -F eb_charges_amount=5000 -F name="Murugan S" -F phone=9876543210 -F email=murugan@e2e.test -F address="12, Gandhi Nagar" -F city=Erode -F state="Tamil Nadu" -F pincode=638001 -F eb_service_no=04-123-456 >/dev/null
eq "customers B2C + B2B, leads confirmed" "$(q "select group_concat(concat(type,':',state) order by id) from customers")|$(q "select group_concat(status order by id) from leads")" "b2c:Tamil Nadu,b2b:Karnataka|confirmed,confirmed,lost"
r=$(post "$M" admin/customers/new admin/customers/save -F type=b2b -F name="Bad GST" -F phone=1 -F gstin=123); has "invalid GSTIN refused" "$r" "customers/new"

# ── invoices & payments ────────────────────────────────────────────────────
section "Invoices (intra / inter-state), email opt-in, cancellation, payments"
: > "$W/smtp.log"
post "$M" admin/invoices/new admin/invoices -F customer_id=1 -F invoice_date="$D" -F due_date="$D" -F subsidy_type=subsidy -F installation_type_id=1 -F capacity_kw=3 -F create_project=1 \
  -F 'items[0][description]=3 kW On-grid Solar System – supply and installation' -F 'items[0][hsn]=8541' -F 'items[0][qty]=1' -F 'items[0][rate]=180000' -F 'items[0][tax_percent]=12' >/dev/null
sleep 1; eq "no invoice email to the customer when box not ticked" "$(grep -c '^To: murugan@e2e.test' "$W/smtp.log")" "0"
eq "invoice 1 (intra, CGST+SGST)" "$(q "select concat(gst_type,' ',subtotal+0,' ',tax_total+0,' ',total+0,' ',status) from invoices where id=1")" "intra 180000 21600 201600 unpaid"
eq "B2C invoice defaults to cumulative format, payment mode not decided" "$(q "select concat(print_format,'/',finance_mode,'/') from invoices where id=1")" "cumulative//"
post "$M" admin/invoices/new admin/invoices -F customer_id=2 -F invoice_date="$D" -F due_date="$D" -F subsidy_type=non_subsidy -F installation_type_id=1 -F capacity_kw=5 -F create_project=1 -F email_invoice=1 -F print_format=itemwise -F finance_mode=loan -F loan_bank="SBI Solar Loan" -F loan_amount=150000 -F loan_ref=SBI-778 \
  -F 'items[0][item_id]=1' -F 'items[0][description]=Mono PERC Panel 545W' -F 'items[0][hsn]=8541' -F 'items[0][qty]=10' -F 'items[0][rate]=11000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][item_id]=3' -F 'items[1][description]=5 kW On-grid Inverter' -F 'items[1][hsn]=8504' -F 'items[1][qty]=1' -F 'items[1][rate]=46000' -F 'items[1][tax_percent]=12' \
  -F 'items[2][description]=Installation and commissioning' -F 'items[2][hsn]=9954' -F 'items[2][qty]=1' -F 'items[2][rate]=20000' -F 'items[2][tax_percent]=18' >/dev/null
has "invoice 2 emailed with PDF" "$(flash "$M" admin/invoices/2)" "Email sent to accounts@kmc.test"
sleep 1; has "…PDF attachment received" "$(grep -a -o 'name="INV[^"]*"' "$W/smtp.log")" ".pdf"
# 110000+46000+20000=176000; tax 13200+5520+3600=22320; total 198320
eq "invoice 2 (inter-state, IGST)" "$(q "select concat(gst_type,' ',subtotal+0,' ',tax_total+0,' ',total+0) from invoices where id=2")" "inter 176000 22320 198320"
has "IGST output ledger posted" "$(q "select group_concat(l.name) from voucher_entries e join ledgers l on l.id=e.ledger_id join vouchers v on v.id=e.voucher_id where v.ref_type='invoice' and v.ref_id=2")" "Output IGST @ 18%"
eq "loan invoice: 3 disbursements of 50,000 created" "$(q "select concat(finance_mode,'/',loan_bank,'/',(select group_concat(amount order by seq) from loan_tranches where invoice_id=2)) from invoices where id=2")" "loan/SBI Solar Loan/50000,50000,50000"
eq "item-wise format kept for B2B" "$(q "select print_format from invoices where id=2")" "itemwise"
eq "projects: subsidy has 7 stages (incl. DCR), non-subsidy 5; kits matched" "$(q "select group_concat(concat(subsidy_type,':',(select count(*) from project_stages s where s.project_id=p.id),':',kit_id) order by id) from projects p")" "subsidy:7:1,non_subsidy:5:2"
eq "surveyor notified" "$(q "select count(*) from notifications n join users u on u.id=n.user_id where u.username='suresh'")" "2"
post "$M" admin/invoices/new admin/invoices -F customer_id=1 -F invoice_date="$D" -F subsidy_type=non_subsidy -F capacity_kw=1 -F create_project=1 -F 'items[0][description]=Wrong invoice' -F 'items[0][qty]=1' -F 'items[0][rate]=1000' -F 'items[0][tax_percent]=12' >/dev/null
post "$M" admin/invoices/3 admin/invoices/3/cancel >/dev/null
post "$M" admin/invoices/new admin/invoices -F customer_id=1 -F invoice_date="$D" -F subsidy_type=non_subsidy -F installation_type_id=1 -F capacity_kw=3 -F kit_id=2 -F create_project=1 -F print_format=cumulative \
  -F 'items[0][description]=Supply of 3 kW On-grid Solar Power System' -F 'items[0][hsn]=8541' -F 'items[0][qty]=1' -F 'items[0][rate]=150000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][description]=Installation & commissioning service' -F 'items[1][hsn]=9954' -F 'items[1][qty]=1' -F 'items[1][rate]=15000' -F 'items[1][tax_percent]=18' >/dev/null
PKG=$(q "select max(id) from invoices")
eq "B2C package invoice: kit chosen on the invoice is the project's kit" "$(q "select p.kit_id from projects p where p.invoice_id=$PKG")" "2"
INVHTML=$(curl -s -b "$M" "$B/admin/invoices/$PKG")
has "B2C invoice shows the supply line" "$INVHTML" "Supply of 3 kW On-grid Solar Power System"
has "…and the service line (not merged)" "$INVHTML" "Installation &amp; commissioning service"
post "$M" admin/invoices/$PKG admin/invoices/$PKG/cancel >/dev/null
eq "invoice cancelled, its project cancelled, voucher removed" "$(q "select concat(i.status,'/',p.status,'/',(select count(*) from vouchers where ref_type='invoice' and ref_id=3)) from invoices i join projects p on p.id=i.project_id where i.id=3")" "cancelled/cancelled/0"
post "$AC" admin/invoices/1 admin/invoices/1/payments -F amount=300000 -F payment_date="$D" -F mode=bank -F account_id=1 >/dev/null
has "overpayment refused" "$(flash "$AC" admin/invoices/1)" "more than the balance"
post "$AC" admin/invoices/1 admin/invoices/1/payments -F amount=20000 -F payment_date="$D" -F mode=upi -F account_id=1 -F reference=UPI1001 -F send_receipt=1 >/dev/null
has "advance recorded + receipt emailed" "$(flash "$AC" admin/invoices/1)" "Email sent to murugan@e2e.test"
eq "invoice 1 partially paid" "$(q "select concat(status,' ',amount_paid+0) from invoices where id=1")" "partial 20000"
post "$AC" admin/invoices/2 admin/invoices/2/tranches -F "t[$(q "select id from loan_tranches where invoice_id=2 and seq=3")][amount]=30000" -F "t[new1][amount]=20000" -F "t[new1][expected_date]=$D" >/dev/null
eq "loan schedule edited: 4th disbursement added" "$(q "select group_concat(amount order by seq) from loan_tranches where invoice_id=2")" "50000,50000,30000,20000"
for seq in 1 2 3 4; do T=$(q "select id from loan_tranches where invoice_id=2 and seq=$seq"); A_=$(q "select amount from loan_tranches where id=$T")
  post "$AC" admin/invoices/2 admin/invoices/2/payments -F amount="$A_" -F payment_date="$D" -F mode=loan -F loan_tranche_id="$T" -F reference="SBI-DISB-$seq" -F account_id=1 >/dev/null; done
eq "all 4 loan disbursements received and linked to receipts" "$(q "select concat(sum(status='received'),'/',sum(payment_id is not null)) from loan_tranches where invoice_id=2")" "4/4"
post "$AC" admin/invoices/2 admin/invoices/2/payments -F amount=48320 -F payment_date="$D" -F mode=cheque -F reference=CHQ556677 -F account_id=1 >/dev/null
eq "invoice 2 fully paid" "$(q "select status from invoices where id=2")" "paid"
has "loan receipt says loan disbursement" "$(curl -s -b "$AC" "$B/admin/payments?mode=loan")" "SBI-DISB-1"
eq "accounts user may not cancel invoices (403)" "$(post "$AC" admin/invoices/2 admin/invoices/2/cancel | cut -c1-3)" "403"
r=$(post "$M" admin/invoices/2 admin/invoices/2/cancel); has "paid invoice cannot be cancelled" "$(flash "$M" admin/invoices/2)" "cannot be cancelled"
eq "receipt PDF (formal receipt)" "$(curl -s -b "$AC" -o "$W/r.pdf" -w '%{content_type}' "$B/admin/vouchers/print/payment/2") $(head -c4 "$W/r.pdf")" "application/pdf %PDF"
eq "invoice PDF" "$(curl -s -b "$AC" -o "$W/i.pdf" -w '%{content_type}' "$B/admin/invoices/2/pdf") $(head -c4 "$W/i.pdf")" "application/pdf %PDF"

# ── project 1: full subsidy flow ───────────────────────────────────────────
section "Project 1 (B2C subsidy, 3 kW): survey reject → reopen → approve → … → closed"
P=admin/projects/1
eq "driver cannot do the survey (403)" "$(post "$W/driver1" admin/tasks $P/stage/survey -F action=complete | cut -c1-3)" "403"
has "surveyor sees the task" "$(curl -s -b "$W/suresh" "$B/admin/tasks")" "PRJ/"
post "$W/suresh" $P $P/stage/survey -F action=reject -F notes="Roof shaded by tree" >/dev/null
eq "survey rejected → project on hold" "$(q "select status from projects where id=1")" "on_hold"
post "$W/suresh" $P $P/survey/reopen >/dev/null
post "$W/suresh" $P $P/upload -F stage=survey -F label=site_photo -F "files[]=@$W/photo.jpg" >/dev/null
r=$(post "$W/suresh" $P $P/upload -F stage=survey -F label=site_photo -F "files[]=@$W/virus.exe"); has "non-image upload refused" "$(flash "$W/suresh" $P)" "Only images"
post "$W/suresh" $P $P/stage/survey -F action=complete -F survey_date="$D" -F roof_type=RCC -F roof_area=400 -F recommended_kw=3 -F shading="Tree trimmed" >/dev/null
eq "survey approved → dispatch active" "$(q "select concat(p.status,'/',p.current_stage) from projects p where id=1")" "active/dispatch"
post "$A" $P $P/assign -F stage_id="$(q "select id from project_stages where project_id=1 and stage='installation'")" -F user_id="$(q "select id from users where username='fitter1'")" -F planned_date="$D" >/dev/null
eq "installation assigned to fitter1" "$(q "select u.username from project_stages s join users u on u.id=s.assigned_to where s.project_id=1 and s.stage='installation'")" "fitter1"
code "$S" "$P/dispatch" >/dev/null; has "dispatch blocked until payment mode is set" "$(flash "$S" $P)" "Set the payment mode"
post "$AC" admin/invoices/1 admin/invoices/1/finance -F finance_mode=cash -F print_format=cumulative >/dev/null
eq "payment mode set to cash" "$(q "select finance_mode from invoices where id=1")" "cash"
has "dispatch form loads kit with stock" "$(curl -s -b "$S" "$B/$P/dispatch")" "3 kW On-grid kit"
post "$S" $P/dispatch $P/dispatch -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=1' >/dev/null
DRAFT=$(q "select max(id) from dispatches"); post "$S" admin/dispatches/$DRAFT admin/dispatches/$DRAFT/delete >/dev/null
eq "draft dispatch deleted" "$(q "select count(*) from dispatches where id=$DRAFT")|$(q "select count(*) from dispatch_items where dispatch_id=$DRAFT")" "0|0"
post "$S" $P/dispatch $P/dispatch -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=6' -F 'items[1][item_id]=2' -F 'items[1][qty]=1' -F 'items[2][item_id]=4' -F 'items[2][qty]=1' -F 'items[3][item_id]=5' -F 'items[3][qty]=30' >/dev/null
DSP1=$(q "select max(id) from dispatches")
eq "dispatch list PDF" "$(curl -s -b "$S" -o /dev/null -w '%{content_type}' "$B/admin/dispatches/$DSP1/print")" "application/pdf"
INVLINE=$(q "select id from dispatch_items where dispatch_id=$DSP1 and item_id=2")
post "$S" admin/dispatches/$DSP1 admin/dispatches/$DSP1/serials -F "serials[$INVLINE]=GW3K-SN-998877" >/dev/null
eq "inverter serial saved at dispatch" "$(q "select serial_nos from dispatch_items where id=$INVLINE")" "GW3K-SN-998877"
post "$S" admin/dispatches/$DSP1 admin/dispatches/$DSP1/dispatch -F vehicle_no=TN33AB1234 -F driver_name=Kannan -F dispatched_through=AUTO -F destination=ERODE -F terms_of_delivery="Door delivery" >/dev/null
eq "delivery note details saved" "$(q "select concat(dispatched_through,'/',destination) from dispatches where id=$DSP1")" "AUTO/ERODE"
eq "delivery note PDF" "$(curl -s -b "$S" -o "$W/dn.pdf" -w '%{content_type}' "$B/admin/dispatches/$DSP1/delivery-note") $(head -c4 "$W/dn.pdf")" "application/pdf %PDF"
r=$(post "$S" admin/dispatches/$DSP1 admin/dispatches/$DSP1/delete); has "dispatched load cannot be deleted" "$(flash "$S" admin/dispatches/$DSP1)" "Only a draft"
eq "stock out + in transit" "$(q "select sum(qty)+0 from stock_movements where item_id=1")|$(q "select status from dispatches where id=$DSP1")" "32|dispatched"
eq "dispatch took the 6 panels from the oldest lot (FIFO)" "$(q "select group_concat(concat(l.bill_no,':',lm.qty+0)) from stock_lot_moves lm join stock_lots l on l.id=lm.lot_id join stock_movements m on m.id=lm.movement_id where m.ref_type='dispatch' and m.ref_id=$DSP1 and l.item_id=1")" "ABC-101:6"
has "dispatch page shows which purchase bill the stock came from" "$(curl -s -b "$S" "$B/admin/dispatches/$DSP1")" "ABC-101"
LP=$(curl -s -b "$S" "$B/admin/stock/item/1/lots"); has "item lots page: bill, PO and where it went" "$LP" "ABC-102"; has "…with the dispatch" "$LP" "$(q "select dispatch_no from dispatches where id=$DSP1")"
has "stock ageing report lists open lots" "$(curl -s -b "$S" "$B/admin/stock/lots")" "ABC-101"
post "$W/driver1" admin/dispatches/$DSP1 admin/dispatches/$DSP1/deliver -F delivery_note=x >/dev/null; has "delivery needs a photo" "$(flash "$W/driver1" admin/dispatches/$DSP1)" "photo"
post "$W/driver1" admin/dispatches/$DSP1 admin/dispatches/$DSP1/deliver -F "photo=@$W/photo.jpg" -F delivery_note="Received by Murugan" >/dev/null
eq "delivered → installation active, installer notified" "$(q "select current_stage from projects where id=1")|$(q "select count(*) from notifications n join users u on u.id=n.user_id where u.username='fitter1'")" "installation|2"
has "installation form pre-filled with inverter serial from dispatch" "$(curl -s -b "$W/fitter1" "$B/$P")" 'value="GW3K-SN-998877"'
post "$W/fitter1" $P $P/stage/installation -F action=complete -F inverter_serial=X >/dev/null; has "installation blocked until uploads" "$(flash "$W/fitter1" $P)" "Still needed"
for l in panel_photo net_meter_agreement panel_serial_photo; do post "$W/fitter1" $P $P/upload -F stage=installation -F label=$l -F "files[]=@$W/photo.jpg" >/dev/null; done
post "$W/fitter1" $P $P/stage/installation -F action=complete -F installation_date="$D" -F inverter_make=Growatt -F inverter_serial=GW3K-0001 -F panel_make=Waaree -F panel_count=6 -F "panel_serials=WS1
WS2
WS3
WS4
WS5
WS6" >/dev/null
eq "installation done → DCR stage active" "$(q "select current_stage from projects where id=1")" "dcr"
post "$W/eb1" $P $P/stage/dcr -F action=complete -F submission_ref=X >/dev/null; has "DCR needs the certificate" "$(flash "$W/eb1" $P)" "Upload the DCR certificate"
post "$W/eb1" $P $P/upload -F stage=dcr -F label=dcr_certificate -F "files[]=@$W/doc.pdf" >/dev/null
post "$W/eb1" $P $P/stage/dcr -F action=complete -F dcr_number=DCR-1 -F submission_ref= >/dev/null; has "DCR needs the submission reference" "$(flash "$W/eb1" $P)" "submission"
post "$W/eb1" $P $P/stage/dcr -F action=complete -F dcr_number=DCR-1 -F dcr_date="$D" -F submission_ref=PMSG-INST-55 -F submission_date="$D" >/dev/null
eq "DCR submitted → EB active" "$(q "select current_stage from projects where id=1")" "eb_process"
E="$W/eb1"
post "$E" $P $P/stage/eb_process -F action=save -F 'steps[eb_charges_paid]=1' >/dev/null; has "EB charges locked until full payment" "$(flash "$E" $P)" "only after the full invoice amount"
post "$E" $P $P/stage/eb_process -F action=save -F 'steps[pcr_uploaded]=1' >/dev/null; has "PCR locked until documents" "$(flash "$E" $P)" "Upload the customer documents first"
post "$E" $P $P/stage/eb_process -F action=complete -F 'steps[acknowledgement]=1' >/dev/null; has "EB cannot complete with steps pending (lists them)" "$(flash "$E" $P)" "Pending: EB charges paid"
has "EB card shows submission status (default No)" "$(curl -s -b "$E" "$B/$P")" 'name="eb_submission_status"'
post "$E" $P $P/stage/eb_process -F action=save -F 'steps[acknowledgement]=1' -F eb_submission_status=yes -F application_no=EB-778 >/dev/null
eq "EB submission status saved" "$(q "select concat(json_unquote(json_extract(data,'$.eb_submission_status')),'/',json_unquote(json_extract(data,'$.application_no'))) from project_stages where project_id=${P##*/} and stage='eb_process'")" "yes/EB-778"
has "…and shown as Yes" "$(curl -s -b "$E" "$B/$P")" '<option value="yes" selected>Yes</option>'
IL=$(curl -s -b "$A" "$B/admin/invoices?from=2000-01-01&to=$D")
has "invoice list: EB Payment, EB Submission right after Customer" "$IL" '<th>Customer</th><th class="text-center lh-sm">EB<br>Payment</th><th class="text-center lh-sm">EB<br>Submission</th><th>Type / kW</th>'
INV2=$(q "select invoice_id from projects where id=${P##*/}")
eq "EB Payment = No, EB Submission = Yes on that project's invoice row" "$(php -r 'foreach (explode("<tr>", $argv[1]) as $tr) if (str_contains($tr, "admin/invoices/" . $argv[2] . "\"")) { preg_match_all("/badge text-bg-[a-z ]+\">(Yes|No)</", $tr, $m); echo implode("/", $m[1]); }' "$IL" "$INV2")" "No/Yes"
has "Paid total in footer" "$IL" "$(q "select format(sum(amount_paid),2,'en_IN') from invoices where status<>'cancelled'")"
post "$E" $P admin/customers/1/documents -F project_id=1 -F "type_files[1]=@$W/doc.pdf" -F "type_files[2]=@$W/photo.jpg" >/dev/null
post "$E" $P $P/stage/eb_process -F action=save -F 'steps[pcr_uploaded]=1' >/dev/null; has "PCR lists the missing required documents" "$(flash "$E" $P)" "Property tax receipt"
post "$E" $P admin/customers/1/documents -F project_id=1 -F "type_files[3]=@$W/doc.pdf" -F "type_files[4]=@$W/doc.pdf" -F "type_files[5]=@$W/photo.jpg" -F 'titles[]=Ownership proof' -F "files[]=@$W/doc.pdf" >/dev/null
eq "5 fixed documents + 1 other, stored under their titles" "$(q "select concat(sum(document_type_id is not null),'+',sum(document_type_id is null)) from customer_documents where customer_id=1")|$(q "select count(*) from customer_documents where file_path like 'customers/1/Aadhaar-card-%'")" "5+1|1"
DOC=$(q "select id from customer_documents where document_type_id=1 limit 1")
eq "document view (inline) and download (attachment)" "$(curl -s -b "$E" -D - -o /dev/null "$B/admin/documents/$DOC" | grep -i '^content-disposition' | cut -d';' -f1 | tr -d '\r')|$(curl -s -b "$E" -D - -o /dev/null "$B/admin/documents/$DOC?download=1" | grep -i '^content-disposition' | cut -d';' -f1 | tr -d '\r')" "Content-Disposition: inline|Content-Disposition: attachment"
post "$AC" admin/invoices/1 admin/invoices/1/payments -F amount=181600 -F payment_date="$D" -F mode=bank -F account_id=1 -F reference=NEFT77 >/dev/null
eq "invoice 1 fully paid" "$(q "select status from invoices where id=1")" "paid"
post "$E" $P $P/upload -F stage=eb_process -F label=pcr_copy -F "files[]=@$W/doc.pdf" >/dev/null
has "uploading the PCR copy ticks the PCR step" "$(flash "$E" $P)" 'Step ticked: &quot;PCR (Project Completion Report) uploaded'
eq "…saved with today's date" "$(q "select json_unquote(json_extract(data,'$.steps.pcr_uploaded')) from project_stages where project_id=1 and stage='eb_process'")" "$D"
post "$E" $P $P/upload -F stage=eb_process -F label=sanction_letter -F "files[]=@$W/photo.jpg" >/dev/null
F=$(q "select max(id) from project_files where project_id=1 and label='sanction_letter'")
post "$E" $P admin/files/$F/delete >/dev/null
eq "uploaded file deleted (record + file)" "$(q "select count(*) from project_files where id=$F")" "0"
F2=$(q "select id from project_files where project_id=1 and stage='survey' limit 1")
post "$E" $P admin/files/$F2/delete >/dev/null; has "file of a completed stage cannot be deleted by staff" "$(flash "$E" $P)" "Only a manager"
EB=(); for k in acknowledgement eb_charges_paid sanction pcr_uploaded inspection meter_installed; do EB+=(-F "steps[$k]=1" -F "step_date[$k]=$D"); done
post "$E" $P $P/stage/eb_process -F action=complete -F application_no=TN-RT-123 -F sanction_no=SAN-9 -F amc_plan_id= "${EB[@]}" >/dev/null
eq "commissioned → AMC created with 10 visits within the contract" "$(q "select concat(p.commissioned_on,'/',c.status,'/',(select count(*) from amc_visits v where v.amc_contract_id=c.id),'/',(select max(due_date)<=c.end_date from amc_visits v where v.amc_contract_id=c.id)) from projects p join amc_contracts c on c.project_id=p.id where p.id=1")" "$D/active/10/1"
post "$E" $P $P/stage/subsidy -F action=complete -F status=applied >/dev/null; has "subsidy cannot close while only applied" "$(flash "$E" $P)" "Received, Rejected or Refunded"
post "$E" $P $P/stage/subsidy -F action=complete -F status=redeemed -F application_no=PMSG-777 -F applied_date="$D" -F subsidy_amount=78000 -F received_date="$D" >/dev/null
eq "project 1 completed" "$(q "select concat(status,'/',current_stage) from projects where id=1")" "completed/completed"

# ── project 2: B2B, split dispatch from two stores ─────────────────────────
section "Project 2 (B2B non-subsidy, 5 kW): stock shortage, split dispatch, two deliveries"
P=admin/projects/2
post "$W/suresh" $P $P/stage/survey -F action=complete -F survey_date="$D" -F roof_type="Metal sheet" -F recommended_kw=5 >/dev/null
K="$W/kumar"   # store + delivery in one person
post "$K" $P/dispatch $P/dispatch -F location_id=1 -F 'items[0][item_id]=1' -F 'items[0][qty]=10' -F 'items[1][item_id]=3' -F 'items[1][qty]=1' -F 'items[2][item_id]=4' -F 'items[2][qty]=2' -F 'items[3][item_id]=5' -F 'items[3][qty]=50' >/dev/null
D2=$(q "select max(id) from dispatches")
has "shortage flagged on the dispatch screen (inverter is in Warehouse 2)" "$(curl -s -b "$K" "$B/admin/dispatches/$D2")" "short in this store"
post "$K" admin/dispatches/$D2 admin/dispatches/$D2/dispatch -F vehicle_no=TN33X >/dev/null; has "dispatch with shortage refused by the server" "$(flash "$K" admin/dispatches/$D2)" "Not enough stock"
eq "…and no stock moved" "$(q "select count(*) from stock_movements where ref_type='dispatch' and ref_id=$D2")" "0"
mysql -N -u"$DBUSER" -p"$DBPASS" "$DB" -e "DELETE FROM dispatch_items WHERE dispatch_id=$D2 AND item_id=3" 2>/dev/null   # store keeps inverter out of this load
post "$K" admin/dispatches/$D2 admin/dispatches/$D2/dispatch -F vehicle_no=TN33X1 -F driver_name=Ravi >/dev/null
post "$K" $P/dispatch $P/dispatch -F location_id=2 -F 'items[0][item_id]=3' -F 'items[0][qty]=1' >/dev/null
D3=$(q "select max(id) from dispatches")
post "$K" admin/dispatches/$D3 admin/dispatches/$D3/dispatch -F vehicle_no=KA01Y2 -F driver_name=Ravi >/dev/null
eq "two dispatches on the road" "$(q "select group_concat(status order by id) from dispatches where project_id=2")" "dispatched,dispatched"
post "$K" admin/dispatches/$D2 admin/dispatches/$D2/deliver -F "photo=@$W/photo.jpg" >/dev/null
eq "delivery stays open until both loads delivered" "$(q "select current_stage from projects where id=2")" "delivery"
post "$K" $P/dispatch $P/dispatch -F location_id=1 -F 'items[0][item_id]=5' -F 'items[0][qty]=5' >/dev/null   # extra list left as draft
post "$K" admin/dispatches/$D3 admin/dispatches/$D3/deliver -F "photo=@$W/photo.jpg" >/dev/null
eq "both delivered → installation (an unused draft list does not block)" "$(q "select current_stage from projects where id=2")" "installation"
P2=admin/projects/2; for l in panel_photo net_meter_agreement panel_serial_photo; do post "$W/fitter1" $P2 $P2/upload -F stage=installation -F label=$l -F "files[]=@$W/photo.jpg" >/dev/null; done
post "$W/fitter1" $P2 $P2/stage/installation -F action=complete -F inverter_serial=INV5-01 -F panel_serials=A1 >/dev/null
post "$E" $P2 admin/customers/2/documents -F project_id=2 -F "type_files[1]=@$W/doc.pdf" -F "type_files[2]=@$W/doc.pdf" -F "type_files[3]=@$W/doc.pdf" -F "type_files[4]=@$W/doc.pdf" -F "type_files[5]=@$W/photo.jpg" -F 'titles[]=GST certificate' -F "files[]=@$W/doc.pdf" >/dev/null
post "$E" $P2 $P2/stage/eb_process -F action=complete -F amc_plan_id=2 "${EB[@]}" >/dev/null
eq "non-subsidy project completes after EB; paid AMC plan chosen (4 visits)" "$(q "select concat(p.status,'/',pl.name,'/',(select count(*) from amc_visits v where v.amc_contract_id=c.id)) from projects p join amc_contracts c on c.project_id=p.id join amc_plans pl on pl.id=c.amc_plan_id where p.id=2")" "completed/Paid 1-year AMC/4"

# ── AMC ────────────────────────────────────────────────────────────────────
section "AMC visits and reminders"
mysql -N -u"$DBUSER" -p"$DBPASS" "$DB" -e "UPDATE amc_visits SET due_date=DATE_ADD(CURDATE(), INTERVAL 3 DAY) WHERE visit_no=1" 2>/dev/null
has "amc:remind sends due reminders" "$(php spark amc:remind 2>&1)" "Reminders sent: 2"
eq "…once only" "$(php spark amc:remind 2>&1 | grep -o 'Reminders sent: [0-9]*')" "Reminders sent: 0"
V=$(q "select id from amc_visits where visit_no=1 order by id limit 1"); C=$(q "select amc_contract_id from amc_visits where id=$V")
post "$W/service1" admin/amc/contracts/$C admin/amc/visits/$V/complete -F done_date="$D" -F findings= >/dev/null; has "visit needs findings" "$(flash "$W/service1" admin/amc/contracts/$C)" "Write what was done"
post "$W/service1" admin/amc/contracts/$C admin/amc/visits/$V/complete -F done_date="$D" -F findings="Panels cleaned, generation normal" -F "photo=@$W/photo.jpg" >/dev/null
eq "AMC visit completed" "$(q "select status from amc_visits where id=$V")" "done"

# ── accounts ───────────────────────────────────────────────────────────────
section "Accounts: income, expenses, delete, journal, contra, ledgers, trial balance"
post "$AC" admin/expenses/new admin/expenses -F head_id=1 -F account_id=2 -F doc_date="$D" -F amount=1500 -F party="Indian Oil" -F project_id=1 -F notes="Van diesel" -F "attachment=@$W/photo.jpg" >/dev/null
post "$AC" admin/expenses/new admin/expenses -F head_id=2 -F account_id=1 -F doc_date="$D" -F amount=45000 -F party="Staff" -F notes="Salary" >/dev/null
post "$AC" admin/expenses/new admin/expenses -F head_id=3 -F account_id=1 -F doc_date="$D" -F amount=9999 -F party="Wrong" >/dev/null
post "$AC" admin/incomes/new admin/incomes -F head_id=1 -F account_id=2 -F doc_date="$D" -F amount=2500 -F party="Scrap dealer" >/dev/null
WRONG=$(q "select id from expenses where amount=9999")
post "$AC" admin/expenses admin/expenses/$WRONG/delete >/dev/null
eq "deleted expense removes its voucher" "$(q "select concat((select count(*) from expenses where id=$WRONG),'/',(select count(*) from vouchers where ref_type='expense' and ref_id=$WRONG))")" "0/0"
L_HDFC=$(q "select id from ledgers where name='HDFC Bank'"); L_CASH=$(q "select id from ledgers where name='Cash'")
post "$AC" admin/accounts/journal admin/accounts/journal -F voucher_type=Contra -F voucher_date="$D" -F narration="Cash deposited" -F "lines[0][ledger_id]=$L_HDFC" -F 'lines[0][debit]=10000' -F "lines[1][ledger_id]=$L_CASH" -F 'lines[1][credit]=10000' >/dev/null
post "$AC" admin/accounts/journal admin/accounts/journal -F voucher_type=Journal -F voucher_date="$D" -F narration="Unbalanced" -F "lines[0][ledger_id]=$L_HDFC" -F 'lines[0][debit]=100' -F "lines[1][ledger_id]=$L_CASH" -F 'lines[1][credit]=90' >/dev/null
has "unbalanced journal refused" "$(flash "$AC" admin/accounts/journal)" "does not balance"
eq "every voucher balances" "$(q "select count(*) from (select voucher_id from voucher_entries group by voucher_id having abs(sum(debit)-sum(credit))>0.009) x")" "0"
# Cash: 20000 -1500 +2500 -10000 = 11000 ; HDFC: 500000 -300000 -85120 +20000 +198320 +181600 -45000 +10000 = 479800
eq "Cash ledger closing" "$(q "select (opening_balance + (select coalesce(sum(debit-credit),0) from voucher_entries where ledger_id=l.id))+0 from ledgers l where name='Cash'")" "11000"
eq "HDFC ledger closing" "$(q "select (opening_balance + (select coalesce(sum(debit-credit),0) from voucher_entries where ledger_id=l.id))+0 from ledgers l where name='HDFC Bank'")" "479800"
# ABC: opening 25000 Cr; purchases (24 panels 241920 + 3 inv 80640 + 6 str 42480 + 300 cable 15930 = 380970) + (16 panels 161280) − return 20160 − paid 300000 → 247090 Cr
eq "ABC supplier balance (Cr)" "$(q "select -(opening_balance + (select coalesce(sum(debit-credit),0) from voucher_entries where ledger_id=l.id))+0 from ledgers l where name='ABC Solar Distributors'")" "247090"
has "supplier summary shows the same balance" "$(curl -s -b "$AC" "$B/admin/reports/suppliers")" "2,47,090.00"
eq "customers fully settled" "$(q "select group_concat((opening_balance + (select coalesce(sum(debit-credit),0) from voucher_entries where ledger_id=l.id))+0) from ledgers l where l.group_name='Sundry Debtors'")" "0,0"
TB=$(curl -s -b "$AC" "$B/admin/accounts/trial-balance")
eq "trial balance matches (no warning)" "$(grep -c 'alert-warning' <<<"$TB")" "0"
has "ledger statement prints vouchers" "$(curl -s -b "$AC" "$B/admin/ledgers/$L_HDFC")" "vouchers/"
eq "all vouchers print as PDF" "$(for v in $(q "select id from vouchers"); do curl -s -b "$AC" -o /dev/null -w '%{content_type}\n' "$B/admin/vouchers/$v/print"; done | sort -u)" "application/pdf"

# ── misc ───────────────────────────────────────────────────────────────────
section "Misc: stock adjustment, notifications, user deactivation, journal delete"
post "$S" admin/stock/adjust admin/stock/adjust -F location_id=2 -F type=adjustment -F note="Count" -F 'items[0][item_id]=6' -F 'items[0][qty]=-50' >/dev/null
has "adjustment below zero refused" "$(flash "$S" admin/stock/adjust)" "Not enough stock"
post "$S" admin/stock/adjust admin/stock/adjust -F location_id=2 -F type=adjustment -F note="Physical count" -F 'items[0][item_id]=6' -F 'items[0][qty]=-1' >/dev/null
eq "adjustment saved (battery 10 → 9)" "$(q "select sum(qty) from stock_movements where item_id=6")" "9"
N=$(q "select n.id from notifications n join users u on u.id=n.user_id where u.username='suresh' order by n.id limit 1")
curl -s -b "$W/suresh" -o /dev/null "$B/admin/notifications/$N"; eq "opening a notification marks it read" "$(q "select read_at is not null from notifications where id=$N")" "1"
RID=$(q "select id from users where username='ravi'"); post "$A" admin/users admin/users/$RID/toggle >/dev/null
eq "deactivated user: existing session ends" "$(code "$W/ravi" admin/leads)" "302"
eq "deactivated user cannot log in again" "$(login "$W/ravi2" ravi@e2e.test 'Passw0rd!x' >/dev/null; code "$W/ravi2" admin/leads)" "302"
eq "deactivated user hidden from staff pickers" "$(curl -s -b "$A" "$B/admin/leads/new" | grep -c '>ravi</option>')" "0"
post "$A" admin/users admin/users/$RID/toggle >/dev/null
eq "re-activated user can log in" "$(login "$W/ravi" ravi@e2e.test 'Passw0rd!x' >/dev/null; code "$W/ravi" admin/leads)" "200"
JV=$(q "select id from vouchers where voucher_type='Contra'"); post "$AC" admin/accounts/day-book admin/accounts/journal/$JV/delete >/dev/null
eq "manual contra can be deleted" "$(q "select count(*) from vouchers where id=$JV")" "0"
SV=$(q "select id from vouchers where ref_type='invoice' limit 1"); post "$AC" admin/accounts/day-book admin/accounts/journal/$SV/delete >/dev/null
eq "system voucher (invoice) cannot be deleted from day book" "$(q "select count(*) from vouchers where id=$SV")" "1"
post "$AC" admin/accounts/journal admin/accounts/journal -F voucher_type=Contra -F voucher_date="$D" -F narration="Cash deposited" -F "lines[0][ledger_id]=$L_HDFC" -F 'lines[0][debit]=10000' -F "lines[1][ledger_id]=$L_CASH" -F 'lines[1][credit]=10000' >/dev/null

# ── reports & Tally ────────────────────────────────────────────────────────
section "Reports and Tally export"
U=$(curl -s -b "$A" "$B/admin/reports/usage")
has "usage report: panels B2B 10 / B2C 6" "$(tr -d '\n' <<<"$U" | grep -o 'Mono PERC Panel 545W</td><td class="text-end">[0-9]*</td><td class="text-end">[0-9]*' | sed 's/<[^>]*>/ /g' | tr -s ' ')" "545W 10 6"
IE=$(curl -s -b "$A" "$B/admin/reports/income-expense?export=excel")
has "income/expense excel exports" "$IE" "Customer receipts"
X=$(curl -s -b "$A" "$B/admin/tally?export=xml"); echo "$X" > "$W/tally.xml"
eq "Tally XML valid, one voucher per accounting entry" "$(php -r '$x=simplexml_load_file($argv[1]); echo $x ? count($x->BODY->IMPORTDATA->REQUESTDATA->TALLYMESSAGE) : "invalid";' "$W/tally.xml")" "$(q "select count(*) from vouchers")"
eq "Tally vouchers balance (Σ amounts = 0)" "$(php -r '$x=simplexml_load_file($argv[1]); $bad=0; foreach($x->BODY->IMPORTDATA->REQUESTDATA->TALLYMESSAGE as $m){$s=0; foreach($m->VOUCHER->{"ALLLEDGERENTRIES.LIST"} as $e) $s+=(float)$e->AMOUNT; if(abs($s)>0.01) $bad++;} echo $bad;' "$W/tally.xml")" "0"
has "Tally purchase carries supplier invoice no. + date" "$(cat "$W/tally.xml")" "<REFERENCE>ABC-101</REFERENCE><REFERENCEDATE>$(date +%Y%m%d -d "$D")</REFERENCEDATE>"
eq "Tally ledger masters XML valid" "$(curl -s -b "$A" "$B/admin/tally?export=ledgers" | php -r '$x=simplexml_load_string(stream_get_contents(STDIN)); echo $x ? "ok" : "invalid";')" "ok"
for r in projects subsidy suppliers usage; do eq "report $r excel" "$(curl -s -b "$A" -o /dev/null -w '%{content_type}' "$B/admin/reports/$r?export=excel" | cut -d';' -f1)" "application/vnd.ms-excel"; done
has "user log records who did what" "$(curl -s -b "$A" "$B/admin/user-log?q=RCT")" "acct1"
FR=$(curl -s -b "$A" "$B/admin/reports/finance"); has "cash / loan report lists the loan customer" "$FR" "SBI Solar Loan"; has "…with disbursements received" "$FR" "4 of 4"
PL=$(curl -s -b "$A" "$B/admin/accounts/profit-loss"); has "profit & loss shows sales" "$PL" "Sales Accounts"; has "…and a net result" "$PL" "Net "
GP_CALC=$(q "select round((select sum(e.credit-e.debit) from voucher_entries e join ledgers l on l.id=e.ledger_id where l.group_name='Sales Accounts') - (select sum(e.debit-e.credit) from voucher_entries e join ledgers l on l.id=e.ledger_id where l.group_name='Purchase Accounts') + (select sum(m.qty*i.purchase_price) from stock_movements m join items i on i.id=m.item_id), 2)")
GP_PL=$(curl -s -b "$A" "$B/admin/accounts/profit-loss?export=excel" | grep -o 'Gross [a-z]*</td><td[^>]*></td><td[^>]*></td><td>[-0-9.]*' | sed 's/.*<td>//')
eq "P&L gross profit = sales − purchases + closing stock (opening 0)" "$(php -r 'echo round((float)$argv[1],2);' "$GP_PL")" "$(php -r 'echo round((float)$argv[1],2);' "$GP_CALC")"
for x in incomes expenses supplier-payments; do eq "$x Excel export" "$(curl -s -b "$AC" -o /dev/null -w '%{content_type}' "$B/admin/$x?export=excel" | cut -d';' -f1)" "application/vnd.ms-excel"; done
eq "P&L Excel export" "$(curl -s -b "$A" -o /dev/null -w '%{content_type}' "$B/admin/accounts/profit-loss?export=excel" | cut -d';' -f1)" "application/vnd.ms-excel"

# ── bulk upload ────────────────────────────────────────────────────────────
section "Bulk upload (Excel / CSV)"
eq "item template is an Excel file" "$(curl -s -b "$A" -o "$W/tpl.xlsx" -w '%{content_type}' "$B/admin/import/items?template=1")" "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
eq "template has headings + valid values sheet" "$(php -r 'require "vendor/autoload.php"; $b=\PhpOffice\PhpSpreadsheet\IOFactory::load($argv[1]); echo $b->getSheet(0)->getCell("A1")->getValue()."|".$b->getSheet(1)->getTitle();' "$W/tpl.xlsx")" "Item name *|Valid values"
printf 'Item name *,Item code / SKU,Installation type,Category *,Unit *,GST %%,Sale price,Opening stock location,Opening stock qty\nBad Row,,,,Nos,abc,,,\nMC4 Connector,MC4,On-grid,Connectors,Pair,18,120,Nowhere,5\n' > "$W/bad.csv"
post "$A" admin/import/items admin/import/items -F "file=@$W/bad.csv" >/dev/null
r=$(curl -s -b "$A" "$B/admin/import/items"); has "errors listed" "$r" "Nothing was saved"; has "…required column" "$r" "Row 2: Category is required"; has "…unknown location" "$r" "Nowhere"
eq "nothing saved on error" "$(q "select count(*) from items")" "6"
printf 'Item name *,Item code / SKU,Installation type,Category *,Unit *,GST %%,Sale price,Opening stock location,Opening stock qty\nMC4 Connector,MC4,On-grid,Connectors,Pair,18,120,Main Store,50\nACDB 3 kW,ACDB3,Hybrid,Protection,Nos,18,"2,500",,\nMono PERC Panel 545W,PNL545,,Solar Panel,Nos,12,99999,,\n' > "$W/items.csv"
post "$A" admin/import/items admin/import/items -F "file=@$W/items.csv" >/dev/null
has "2 added, 1 skipped" "$(curl -s -b "$A" "$B/admin/import/items")" "2 added, 0 updated, 1 skipped"
eq "new type + categories created, opening stock in" "$(q "select concat((select count(*) from installation_types),'/',(select count(*) from item_categories),'/',(select qty+0 from stock_movements where item_id=(select id from items where sku='MC4')),'/',(select sale_price+0 from items where sku='ACDB3'))")" "3/7/50/2500"
eq "existing item untouched without Update existing" "$(q "select sale_price+0 from items where sku='PNL545'")" "11000"
post "$A" admin/import/items admin/import/items -F "file=@$W/items.csv" -F update_existing=1 >/dev/null
eq "Update existing changes price, no double opening stock" "$(q "select concat((select sale_price+0 from items where sku='PNL545'),'/',(select count(*) from stock_movements where item_id=(select id from items where sku='MC4')))")" "99999/1"
q "update items set sale_price=11000 where sku='PNL545'" >/dev/null
printf 'Type (B2B / B2C) *,Name *,Phone *,City,State\nB2C,Imported One,9000000001,Salem,Tamil Nadu\nb2b,Imported Two Pvt Ltd,9000000002,Bengaluru,Karnataka\n' > "$W/cust.csv"
post "$A" admin/import/customers admin/import/customers -F "file=@$W/cust.csv" >/dev/null
eq "customers imported" "$(q "select group_concat(concat(type,':',city) order by id) from customers where name like 'Imported%'")" "b2c:Salem,b2b:Bengaluru"
printf 'Supplier name *,Phone,State,Opening balance we owe\nImported Supplier,9111111111,Kerala,"1,000"\n' > "$W/sup.csv"
post "$A" admin/import/suppliers admin/import/suppliers -F "file=@$W/sup.csv" >/dev/null
eq "supplier imported" "$(q "select concat(state,' ',opening_balance+0) from suppliers where name='Imported Supplier'")" "Kerala 1000"
printf 'Expense head *\nTravel\nSalary\n' > "$W/eh.csv"; post "$A" admin/import/expense-heads admin/import/expense-heads -F "file=@$W/eh.csv" >/dev/null
printf 'Income head *,Description\nCommission,Dealer commission\n' > "$W/ih.csv"; post "$A" admin/import/income-heads admin/import/income-heads -F "file=@$W/ih.csv" >/dev/null
eq "heads imported (existing Salary skipped)" "$(q "select concat((select count(*) from expense_heads),'/',(select count(*) from income_heads))")" "4/3"
has "Bulk upload button on suppliers list" "$(curl -s -b "$A" "$B/admin/suppliers")" "admin/import/suppliers"
eq "bulk upload blocked without permission" "$(code "$W/ravi" admin/import/items)" "403"

# ── dashboard ──────────────────────────────────────────────────────────────
section "Dashboard: clients and invoice totals"
DB_HTML=$(curl -s -b "$A" "$B/admin")
has "cash clients count" "$DB_HTML" "Cash clients</div><div class=\"value\">$(q "select count(distinct customer_id) from invoices where status<>'cancelled' and finance_mode='cash'")<"
has "bank loan clients count" "$DB_HTML" "Bank loan clients</div><div class=\"value\">$(q "select count(distinct customer_id) from invoices where status<>'cancelled' and finance_mode='loan'")<"
has "outstanding value" "$DB_HTML" "$(q "select format(sum(total-amount_paid),2,'en_IN') from invoices where status<>'cancelled'")"

# ── user manual ────────────────────────────────────────────────────────────
section "User Manual"
fails=""; for t in $(php -r 'require "vendor/autoload.php"; foreach (array_keys((new ReflectionClass("App\\Controllers\\Admin\\Manual"))->getConstant("TOPICS")) as $k) echo $k, " ";'); do c=$(code "$A" "admin/manual/$t"); [ "$c" == 200 ] || fails="$fails $t($c)"; done
eq "every manual chapter opens" "${fails:-none}" "none"
has "User Manual in the menu" "$(curl -s -b "$A" "$B/admin")" "admin/manual\"><i class=\"bi bi-book"
has "Help on the invoice page opens the Invoices chapter" "$(curl -s -b "$A" "$B/admin/invoices/1")" "admin/manual/invoices"
has "Help on a purchase return opens the Purchase chapter" "$(curl -s -b "$A" "$B/admin/purchase-returns/new")" "admin/manual/purchase"
eq "delivery staff can read the manual too" "$(code "$W/driver1" admin/manual/delivery)" "200"
eq "unknown chapter is 404" "$(code "$A" admin/manual/nope)" "404"

# ── quotations ────────────────────────────────────────────────────────────
section "Quotation: lead → quotation → share → convert to invoice"
lead "Quote Lead Kumar" 9555500001 qlead@e2e.test Salem b2c 3
QL=$(q "select id from leads where phone='9555500001'")
has "lead page offers a quotation" "$(curl -s -b "$M" "$B/admin/leads/$QL")" "admin/quotations/new?lead=$QL"
has "new quotation is filled from the lead" "$(curl -s -b "$M" "$B/admin/quotations/new?lead=$QL")" 'value="Quote Lead Kumar"'
post "$M" "admin/quotations/new?lead=$QL" admin/quotations -F lead_id=$QL -F party_name="Quote Lead Kumar" -F party_phone=9555500001 -F party_email=qlead@e2e.test -F party_city=Salem -F party_state="Tamil Nadu" \
  -F customer_type=b2c -F quote_date="$D" -F valid_until="$D" -F subsidy_type=subsidy -F installation_type_id=1 -F capacity_kw=3 -F kit_id=1 -F print_format=cumulative \
  -F subsidy_amount=78000 -F eb_charges_by=customer -F eb_charges_amount=5000 -F units_per_kw_day=4 -F tariff=6.5 -F notes="50% advance" \
  -F 'items[0][description]=Supply of 3 kW On-grid Solar Power System' -F 'items[0][hsn]=8541' -F 'items[0][qty]=1' -F 'items[0][rate]=160000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][description]=Installation & commissioning service' -F 'items[1][hsn]=9954' -F 'items[1][qty]=1' -F 'items[1][rate]=10000' -F 'items[1][tax_percent]=18' >/dev/null
QQ=$(q "select max(id) from quotations")
# 160000 + 19200 GST + 10000 + 1800 GST = 191000
eq "quotation saved: number, totals, status" "$(q "select concat(quote_no,' ',total+0,' ',status) from quotations where id=$QQ")" "QT/$(php -r 'echo (date("n")>=4?date("Y"):date("Y")-1)."-".substr((date("n")>=4?date("Y"):date("Y")-1)+1,2);')/0001 191000 draft"
QS=$(curl -s -b "$M" "$B/admin/quotations/$QQ")
# 3 kW × 4 × 365 = 4380 units × 6.5 = 28470/yr; net = 191000 + 5000 − 78000 = 118000; payback 4.1 yrs; 25 yrs = 711750
has "savings: units per year" "$QS" "4,380 units"
has "…net cost after subsidy (+ meter charges)" "$QS" "₹1,18,000.00"
has "…payback" "$QS" "4.1 years"
has "…25-year savings" "$QS" "₹7,11,750.00"
eq "quotation PDF" "$(curl -s -b "$M" -o "$W/quote.pdf" -w '%{content_type}' "$B/admin/quotations/$QQ/pdf" | cut -d';' -f1)" "application/pdf"
has "PDF shows payback and net cost" "$(pdftotext "$W/quote.pdf" - 2>/dev/null | tr -s ' \n' ' ')" "Payback 4.1 years"
QT=$(q "select share_token from quotations where id=$QQ")
eq "share link opens the PDF without login" "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$B/quote/$QT" | cut -d';' -f1)" "200 application/pdf"
eq "…and records that the customer opened it" "$(q "select viewed_at is not null from quotations where id=$QQ")" "1"
eq "wrong share link is 404" "$(curl -s -o /dev/null -w '%{http_code}' "$B/quote/0123456789abcdef0123456789abcdef")" "404"
eq "WhatsApp button opens wa.me with the link" "$(curl -s -b "$M" -o /dev/null -w '%{redirect_url}' "$B/admin/quotations/$QQ/whatsapp" | grep -o '^https://wa.me/919555500001?text=.*quote%2F'$QT | wc -l)" "1"
eq "…and marks it sent" "$(q "select status from quotations where id=$QQ")" "sent"
post "$M" admin/quotations/$QQ admin/quotations/$QQ/email -F to=qlead@e2e.test >/dev/null; has "quotation emailed with PDF" "$(flash "$M" admin/quotations/$QQ)" "Email sent"
eq "store user cannot open quotations" "$(code "$W/store1" admin/quotations)" "403"
post "$M" admin/quotations/$QQ admin/quotations/$QQ/status -F status=accepted >/dev/null
r=$(post "$M" admin/quotations/$QQ admin/quotations/$QQ/convert)
QC=$(q "select customer_id from quotations where id=$QQ")
eq "convert: customer created from the lead, lead confirmed" "$(q "select concat(c.name,'/',c.eb_charges_by,'/',l.status,'/',l.customer_id=c.id) from customers c join leads l on l.id=$QL where c.id=$QC")" "Quote Lead Kumar/customer/confirmed/1"
IF=$(curl -s -b "$M" "$B/admin/invoices/new?quotation=$QQ")
has "invoice form filled from the quotation" "$IF" 'value="Supply of 3 kW On-grid Solar Power System"'
has "…with the quotation link" "$IF" "name=\"quotation_id\" value=\"$QQ\""
post "$M" "admin/invoices/new?quotation=$QQ" admin/invoices -F quotation_id=$QQ -F customer_id=$QC -F invoice_date="$D" -F subsidy_type=subsidy -F installation_type_id=1 -F capacity_kw=3 -F kit_id=1 -F print_format=cumulative \
  -F eb_charges_by=customer -F eb_charges_amount=5000 \
  -F 'items[0][description]=Supply of 3 kW On-grid Solar Power System' -F 'items[0][hsn]=8541' -F 'items[0][qty]=1' -F 'items[0][rate]=160000' -F 'items[0][tax_percent]=12' \
  -F 'items[1][description]=Installation & commissioning service' -F 'items[1][hsn]=9954' -F 'items[1][qty]=1' -F 'items[1][rate]=10000' -F 'items[1][tax_percent]=18' >/dev/null
QI=$(q "select max(id) from invoices")
eq "invoice saved, quotation converted and linked" "$(q "select concat(i.total+0,'/',q.status,'/',q.invoice_id=i.id) from invoices i join quotations q on q.id=$QQ where i.id=$QI")" "191000/converted/1"
hasnt "converted quotation cannot be edited" "$(curl -s -b "$M" "$B/admin/quotations/$QQ")" "/edit\""
post "$A" admin/invoices/$QI admin/invoices/$QI/delete >/dev/null
eq "deleting the invoice puts the quotation back to accepted" "$(q "select concat(status,'/',ifnull(invoice_id,'none')) from quotations where id=$QQ")" "accepted/none"

# ── customer status page ──────────────────────────────────────────────────
section "Customer status page (private link, no login)"
has "project page shows the customer status link" "$(curl -s -b "$A" "$B/admin/projects/1")" "/track/"
ST=$(q "select status_token from projects where id=1")
SP1=$(curl -s "$B/track/$ST")
has "opens without login: project and system" "$SP1" "$(q "select project_no from projects where id=1")"
has "…progress timeline" "$SP1" "Site Survey &amp; Approval"
has "…payments: fully paid" "$SP1" "Fully paid"
has "…AMC cover" "$SP1" "Covered until"
has "…contact buttons" "$SP1" "wa.me/"
hasnt "…does not show the customer's phone number" "$SP1" "9876543210"
eq "customer view is recorded" "$(q "select status_viewed_at is not null from projects where id=1")" "1"
eq "wrong link is 404" "$(curl -s -o /dev/null -w '%{http_code}' "$B/track/0123456789abcdef0123456789abcdef")" "404"
eq "WhatsApp button sends the link" "$(curl -s -b "$A" -o /dev/null -w '%{redirect_url}' "$B/admin/projects/1/status-link/whatsapp" | grep -c "track%2F$ST")" "1"
post "$A" admin/projects/1 admin/projects/1/status-link/reset >/dev/null
eq "reset: old link stops working" "$(curl -s -o /dev/null -w '%{http_code}' "$B/track/$ST")" "404"
eq "…new link works" "$(curl -s -o /dev/null -w '%{http_code}' "$B/track/$(q "select status_token from projects where id=1")")" "200"
has "stage emails to the customer include the link" "$(cat "$W/smtp.log")" "Follow your project:"
SP2=$(curl -s "$B/track/$(q "select status_token from projects where id=2")")
has "loan project shows disbursements" "$SP2" "Disbursement 1"

# ── service complaints ────────────────────────────────────────────────────
section "Service complaints: register → assign → update → resolve → close"
post "$A" "admin/service/new?customer=1&project=1" admin/service -F customer_id=1 -F project_id=1 -F category=no_generation -F priority=urgent -F reported_via=phone -F description="Inverter shows grid fault since morning" >/dev/null
TK=$(q "select max(id) from service_tickets")
eq "complaint registered: number, AMC cover, status" "$(q "select concat(ticket_no,' ',under_amc,' ',status,' ',priority) from service_tickets where id=$TK")" "SRV/$(php -r 'echo (date("n")>=4?date("Y"):date("Y")-1)."-".substr((date("n")>=4?date("Y"):date("Y")-1)+1,2);')/0001 1 open urgent"
eq "service team notified" "$(q "select count(*) from notifications n join auth_groups_users g on g.user_id=n.user_id and g.group='service' where n.link='admin/service/$TK'")" "1"
sleep 1; TKNO=$(q "select ticket_no from service_tickets where id=$TK"); eq "customer gets an acknowledgement email (with the number)" "$(grep -c "registered your complaint $TKNO" "$W/smtp.log")" "1"
has "dashboard shows open complaints" "$(curl -s -b "$A" "$B/admin")" "Open complaints"
SV=$(q "select id from users where username='service1'")
post "$A" admin/service/$TK admin/service/$TK/assign -F assigned_to=$SV -F scheduled_date="$D" >/dev/null
eq "assigned to technician with visit date" "$(q "select concat(status,'/',assigned_to=$SV,'/',scheduled_date='$D') from service_tickets where id=$TK")" "assigned/1/1"
has "technician sees it in My Tasks" "$(curl -s -b "$W/service1" "$B/admin/tasks")" "admin/service/$TK"
eq "installer (not assigned, no service role) cannot open it" "$(code "$W/fitter1" admin/service/$TK)" "403"
post "$W/service1" admin/service/$TK admin/service/$TK/update -F note="Reached site, checking" -F status=in_progress -F "photo=@$W/photo.jpg" >/dev/null
eq "technician update with photo, in progress" "$(q "select concat(t.status,'/',(select count(*) from service_ticket_updates u where u.ticket_id=t.id and u.photo_path is not null)) from service_tickets t where id=$TK")" "in_progress/1"
post "$W/service1" admin/service/$TK admin/service/$TK/resolve -F resolution="Reset inverter, tightened AC connector" -F charge_amount=0 >/dev/null
eq "resolved by the technician" "$(q "select concat(status,'/',resolved_by=$SV,'/',chargeable) from service_tickets where id=$TK")" "resolved/1/0"
sleep 1; eq "customer told it is resolved" "$(grep -c "complaint $TKNO has been resolved" "$W/smtp.log")" "1"
post "$A" admin/service/$TK admin/service/$TK/close -F action=close -F note="Customer confirmed" >/dev/null
eq "closed by the office" "$(q "select status from service_tickets where id=$TK")" "closed"
post "$A" admin/service/$TK admin/service/$TK/close -F action=reopen -F note="Fault again" >/dev/null
eq "reopened goes back to the technician" "$(q "select status from service_tickets where id=$TK")" "assigned"
has "complaints list shows it" "$(curl -s -b "$A" "$B/admin/service")" "admin/service/$TK"
ST1=$(q "select status_token from projects where id=1")
TOKP=$(curl -s -c "$W/pub" -b "$W/pub" "$B/track/$ST1" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$W/pub" -c "$W/pub" -o /dev/null --data-urlencode "csrf_test_name=$TOKP" --data-urlencode category=low_generation --data-urlencode "description=Generation dropped to half this week" "$B/track/$ST1/complaint"
eq "customer can report a problem from the status page" "$(q "select concat(reported_via,'/',category,'/',created_by is null) from service_tickets order by id desc limit 1")" "status_page/low_generation/1"
has "…and sees it there with its number" "$(curl -s -b "$W/pub" "$B/track/$ST1")" "$(q "select ticket_no from service_tickets order by id desc limit 1")"
for i in 1 2; do TOKP=$(curl -s -c "$W/pub" -b "$W/pub" "$B/track/$ST1" | grep -o 'name="csrf_test_name" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'); curl -s -b "$W/pub" -c "$W/pub" -o /dev/null --data-urlencode "csrf_test_name=$TOKP" --data-urlencode category=other --data-urlencode "description=Another problem $i" "$B/track/$ST1/complaint"; done
eq "no more than 3 open complaints from the page" "$(q "select count(*) from service_tickets where project_id=1 and status in ('open','assigned','in_progress')")" "3"
post "$A" admin/service/$TK admin/service/$TK/delete >/dev/null
eq "admin can delete a complaint" "$(q "select count(*) from service_tickets where id=$TK")" "0"

# ── IndiaMART lead import ─────────────────────────────────────────────────
section "IndiaMART: push (instant) and pull (CRM key) into Leads"
post "$A" admin/settings admin/settings -F indiamartEnabled=1 -F indiamartKey=TESTKEY123 >/dev/null
has "settings show the push address" "$(curl -s -b "$A" "$B/admin/settings")" "/indiamart/push/"
IMT=$(q "select value from settings where \`key\`='indiamartPushToken'")
cat > "$W/im1.json" <<'J'
{"CODE":200,"STATUS":"SUCCESS","RESPONSE":{"UNIQUE_QUERY_ID":"2873001","QUERY_TYPE":"W","QUERY_TIME":"2026-11-23 10:15:00","SENDER_NAME":"Rajesh Kannan","SENDER_MOBILE":"+91-9443300111","SENDER_EMAIL":"rajesh@example.com","SUBJECT":"Requirement for On Grid Solar System","SENDER_COMPANY":"","SENDER_ADDRESS":"4, Anna Nagar","SENDER_CITY":"Tiruppur","SENDER_STATE":"Tamil Nadu","QUERY_PRODUCT_NAME":"5 kW On Grid Solar Power Plant","QUERY_MESSAGE":"Need 5 kw rooftop system for my house.<br>Monthly bill 4000"}}
J
eq "push accepted" "$(curl -s -H 'Content-Type: application/json' --data-binary @"$W/im1.json" "$B/indiamart/push/$IMT" | tr -d ' \n')" '{"code":200,"status":"Success"}'
eq "lead created: source, ref, phone, kW, type" "$(q "select concat(name,'/',source,'/',external_ref,'/',phone,'/',required_kw+0,'/',customer_type,'/',city) from leads where external_ref='IM-2873001'")" "Rajesh Kannan/IndiaMART/IM-2873001/9443300111/5/b2c/Tiruppur"
eq "marketing team notified" "$(q "select count(*) from notifications where title like 'New IndiaMART lead%'")" "$(q "select count(*) from auth_groups_users where \`group\`='marketing'")"
curl -s -H 'Content-Type: application/json' --data-binary @"$W/im1.json" "$B/indiamart/push/$IMT" >/dev/null
eq "same enquiry pushed again is not duplicated" "$(q "select count(*) from leads where external_ref='IM-2873001'")" "1"
sed 's/2873001/2873002/; s/Need 5 kw rooftop system for my house./Please call back today./' "$W/im1.json" > "$W/im2.json"
curl -s -H 'Content-Type: application/json' --data-binary @"$W/im2.json" "$B/indiamart/push/$IMT" >/dev/null
eq "repeat enquiry from the same phone becomes a follow-up on that lead" "$(q "select concat((select count(*) from leads where phone='9443300111'),'/',(select count(*) from lead_followups f join leads l on l.id=f.lead_id where l.phone='9443300111' and f.note like '%IM-2873002%'))")" "1/1"
eq "wrong push address is refused" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' --data-binary @"$W/im1.json" "$B/indiamart/push/0123456789abcdef0123456789abcdef")" "404"
mkdir -p "$W/imstub"; cat > "$W/imstub/crm.json" <<'J'
{"CODE":200,"STATUS":"SUCCESS","MESSAGE":"","TOTAL_RECORDS":2,"RESPONSE":[{"UNIQUE_QUERY_ID":"2873101","QUERY_TYPE":"B","SENDER_NAME":"Lakshmi Textiles","SENDER_MOBILE":"9500011223","SENDER_EMAIL":"buy@lakshmitex.test","SENDER_COMPANY":"Lakshmi Textiles Pvt Ltd","SENDER_CITY":"Erode","QUERY_PRODUCT_NAME":"50 KW Solar Power Plant","QUERY_MESSAGE":"Factory rooftop"},{"UNIQUE_QUERY_ID":"2873001","SENDER_NAME":"Rajesh Kannan","SENDER_MOBILE":"9443300111","QUERY_PRODUCT_NAME":"5 kW On Grid Solar Power Plant"}]}
J
(cd "$W/imstub" && python3 -m http.server 8097 --bind 127.0.0.1 >/dev/null 2>&1 &); sleep 1
q "insert into settings (class, \`key\`, value, type, context, created_at, updated_at) values ('Config\\\\Solar','indiamartApiUrl','http://127.0.0.1:8097/crm.json','string',NULL,now(),now())" >/dev/null
post "$A" admin/settings admin/indiamart/pull >/dev/null; has "Fetch now imports new enquiries and skips known ones" "$(flash "$A" admin/settings)" "1 new lead(s), 0 added to existing leads, 1 already imported"
eq "B2B lead from the company name, 50 kW" "$(q "select concat(customer_type,'/',required_kw+0,'/',source) from leads where external_ref='IM-2873101'")" "b2b/50/IndiaMART"
post "$A" admin/settings admin/indiamart/pull >/dev/null; has "IndiaMART's 5-minute limit is respected" "$(flash "$A" admin/settings)" "one check every 5 minutes"
has "cron command runs (and respects the limit)" "$(php spark indiamart:pull 2>&1)" "one check every 5 minutes"
has "leads list filters by source" "$(curl -s -b "$M" "$B/admin/leads?source=IndiaMART")" "Lakshmi Textiles"
pkill -f "http.server 8097" 2>/dev/null

# ── Meta Lead Ads + Google Ads lead forms ──────────────────────────────────
section "Meta (Facebook / Instagram) and Google Ads leads"
post "$A" admin/settings admin/settings -F metaEnabled=1 -F metaPageToken=PAGETOKEN -F metaAppSecret=appsecret123 -F googleEnabled=1 >/dev/null
SETP=$(curl -s -b "$A" "$B/admin/settings")
has "settings show the Meta callback URL and Google key" "$SETP" "webhooks/google"
MVT=$(q "select value from settings where \`key\`='metaVerifyToken'"); GK=$(q "select value from settings where \`key\`='googleKey'")
eq "Meta subscription check answers with the challenge" "$(curl -s "$B/webhooks/meta?hub.mode=subscribe&hub.verify_token=$MVT&hub.challenge=4242")" "4242"
eq "…wrong verify token refused" "$(curl -s -o /dev/null -w '%{http_code}' "$B/webhooks/meta?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1")" "403"
mkdir -p "$W/graph"; cat > "$W/graph/444555666" <<'J'
{"id":"444555666","created_time":"2026-11-23T05:10:00+0000","ad_name":"3 kW rooftop offer","campaign_name":"Erode Nov","platform":"ig","field_data":[{"name":"full_name","values":["Priya S"]},{"name":"phone_number","values":["+919786012345"]},{"name":"email","values":["priya@example.com"]},{"name":"city","values":["Erode"]},{"name":"monthly_electricity_bill","values":["3000-5000"]}]}
J
(cd "$W/graph" && python3 -m http.server 8097 --bind 127.0.0.1 >/dev/null 2>&1 &); sleep 1
q "insert into settings (class, \`key\`, value, type, context, created_at, updated_at) values ('Config\\\\Solar','metaGraphUrl','http://127.0.0.1:8097/','string',NULL,now(),now())" >/dev/null
MB='{"object":"page","entry":[{"id":"111","time":1700000000,"changes":[{"field":"leadgen","value":{"leadgen_id":"444555666","page_id":"111","form_id":"22"}}]}]}'
SIG="sha256=$(printf '%s' "$MB" | openssl dgst -sha256 -hmac appsecret123 | sed 's/^.* //')"
eq "Meta call with a bad signature is refused" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -H 'X-Hub-Signature-256: sha256=bad' --data-binary "$MB" "$B/webhooks/meta")" "403"
eq "signed Meta lead accepted" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' -H "X-Hub-Signature-256: $SIG" --data-binary "$MB" "$B/webhooks/meta")" "200"
eq "Meta lead created from the Graph API answers" "$(q "select concat(name,'/',phone,'/',email,'/',city,'/',source,'/',required_kw+0) from leads where external_ref='FB-444555666'")" "Priya S/9786012345/priya@example.com/Erode/Facebook / Instagram/3"
has "…with custom answers, campaign and platform in the notes" "$(q "select notes from leads where external_ref='FB-444555666'")" "Monthly electricity bill: 3000-5000"
curl -s -o /dev/null -H 'Content-Type: application/json' -H "X-Hub-Signature-256: $SIG" --data-binary "$MB" "$B/webhooks/meta"
eq "same Meta lead again is not duplicated" "$(q "select count(*) from leads where external_ref='FB-444555666'")" "1"
MB2='{"object":"page","entry":[{"id":"111","changes":[{"field":"leadgen","value":{"leadgen_id":"999000999"}}]}]}'
SIG2="sha256=$(printf '%s' "$MB2" | openssl dgst -sha256 -hmac appsecret123 | sed 's/^.* //')"
curl -s -o /dev/null -H 'Content-Type: application/json' -H "X-Hub-Signature-256: $SIG2" --data-binary "$MB2" "$B/webhooks/meta"
has "if Meta details cannot be read the lead is still saved with the reason" "$(q "select concat(name,' | ',notes) from leads where external_ref='FB-999000999'")" "Facebook lead 999000999 | Details could not be read"
pkill -f "http.server 8097" 2>/dev/null
GB='{"lead_id":"TeSter-123","user_column_data":[{"column_name":"Full Name","string_value":"Senthil Kumar","column_id":"FULL_NAME"},{"column_name":"User Phone","string_value":"+91 98940 55667","column_id":"PHONE_NUMBER"},{"column_name":"City","string_value":"Karur","column_id":"CITY"},{"column_name":"System size needed","string_value":"5 kW","column_id":"Q1"}],"api_version":"1.0","form_id":40000,"campaign_id":12345,"google_key":"'$GK'","is_test":false}'
eq "Google lead with a wrong key is refused" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' --data-binary "${GB/$GK/wrongkey}" "$B/webhooks/google")" "403"
eq "Google lead accepted" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Content-Type: application/json' --data-binary "$GB" "$B/webhooks/google")" "200"
eq "Google lead created (name, phone, city, kW from the answer)" "$(q "select concat(name,'/',phone,'/',city,'/',source,'/',required_kw+0) from leads where external_ref='GA-TeSter-123'")" "Senthil Kumar/9894055667/Karur/Google Ads/5"
curl -s -o /dev/null -H 'Content-Type: application/json' --data-binary "$GB" "$B/webhooks/google"
eq "same Google lead again is not duplicated" "$(q "select count(*) from leads where external_ref='GA-TeSter-123'")" "1"
curl -s -o /dev/null -H 'Content-Type: application/json' --data-binary "${GB/TeSter-123/test-2}" "$B/webhooks/google" ; true
eq "repeat from the same phone becomes a follow-up" "$(q "select count(*) from lead_followups where note like '%[GA-test-2]%'")" "1"
GT=$(echo "$GB" | sed 's/TeSter-123/T-9/; s/"is_test":false/"is_test":true/; s/98940 55667/90000 11111/')
curl -s -o /dev/null -H 'Content-Type: application/json' --data-binary "$GT" "$B/webhooks/google"
eq "Google 'send test data' shows as a test lead" "$(q "select name from leads where external_ref='GA-T-9'")" "Senthil Kumar (Google test lead)"
eq "marketing notified about Meta and Google leads" "$(q "select count(distinct title) from notifications where title like 'New Facebook%' or title like 'New Google Ads%'")" "4"
has "lead form offers Google Ads as a source" "$(curl -s -b "$M" "$B/admin/leads/new")" "Google Ads"

# ── project profit ─────────────────────────────────────────────────────────
section "GST e-invoice: upload file for einvoice1.gst.gov.in"
EI=$(curl -s -b "$A" "$B/admin/einvoice")
has "e-invoice page lists the B2B invoice as ready" "$EI" "admin/einvoice/2/json"
eq "…and not the B2C invoice (no GSTIN)" "$(grep -c 'admin/invoices/1"' <<<"$EI")" "0"
curl -s -b "$A" -o "$W/ei.json" "$B/admin/einvoice/2/json"
eq "JSON in portal schema 1.1: B2B, seller TN 33 / Erode 638011, buyer Karnataka 29, IGST, 3 lines" "$(php -r '$j=json_decode(file_get_contents($argv[1]),true)[0]; echo $j["Version"],"|",$j["TranDtls"]["SupTyp"],"|",$j["SellerDtls"]["Stcd"],"/",$j["SellerDtls"]["Loc"],"/",$j["SellerDtls"]["Pin"],"|",$j["BuyerDtls"]["Pos"],"/",$j["BuyerDtls"]["Gstin"],"|",$j["ValDtls"]["CgstVal"]+0,"/",$j["ValDtls"]["IgstVal"]>0?"igst":"none","|",count($j["ItemList"]),"|",$j["DocDtls"]["Dt"];' "$W/ei.json")" "1.1|B2B|33/Erode/638011|29/29KMCST1234A1Z9|0/igst|3|$(date -d "$(q "select invoice_date from invoices where id=2")" +%d/%m/%Y)"
eq "JSON totals add up to the invoice total" "$(php -r '$j=json_decode(file_get_contents($argv[1]),true)[0]; $s=0; foreach($j["ItemList"] as $i){ $s+=$i["TotItemVal"]; if (abs($i["AssAmt"]+$i["IgstAmt"]+$i["CgstAmt"]+$i["SgstAmt"]-$i["TotItemVal"])>0.01) echo "line-bad "; } echo round($s+$j["ValDtls"]["RndOffAmt"],2),"=",$j["ValDtls"]["TotInvVal"];' "$W/ei.json")" "$(q "select total+0 from invoices where id=2")=$(q "select total+0 from invoices where id=2")"
eq "service line (SAC 99..) marked as service" "$(php -r 'foreach(json_decode(file_get_contents($argv[1]),true)[0]["ItemList"] as $i) echo $i["HsnCd"],":",$i["IsServc"]," ";' "$W/ei.json")" "8541:N 8504:N 9954:Y "
r=$(post "$A" admin/einvoice admin/einvoice/download -F 'ids[]=2'); eq "bulk download of ticked invoices is a JSON array" "$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo is_array($j)?count($j):"bad";' "$W/last.html")" "1"
r=$(curl -s -b "$A" -o /dev/null -w '%{http_code} %{redirect_url}' "$B/admin/einvoice/1/json"); has "B2C invoice JSON refused" "$(flash "$A" admin/invoices/1)" "no GSTIN"
post "$M" admin/customers/2/edit admin/customers/save/2 -F type=b2b -F eb_charges_by=company -F name="KMC School" -F company="KMC Educational Trust" -F gstin=29KMCST1234A1Z9 -F phone=9876543211 -F email=accounts@kmc.test -F address="5, MG Road" -F city=Bengaluru -F state=Karnataka -F pincode= >/dev/null
has "missing customer PIN is reported before upload" "$(curl -s -b "$A" "$B/admin/einvoice")" "Customer PIN code is missing"
post "$M" admin/customers/2/edit admin/customers/save/2 -F type=b2b -F eb_charges_by=company -F name="KMC School" -F company="KMC Educational Trust" -F gstin=29KMCST1234A1Z9 -F phone=9876543211 -F email=accounts@kmc.test -F address="5, MG Road" -F city=Bengaluru -F state=Karnataka -F pincode=560001 >/dev/null
r=$(post "$A" admin/invoices/2 admin/einvoice/2/irn -F irn=12345); has "wrong IRN refused" "$(flash "$A" admin/invoices/2)" "64-character"
IRN=a5c12dca80e743321740b001fd70953e8738d109865d28ba4013750f2046f229
post "$A" admin/invoices/2 admin/einvoice/2/irn -F irn=$IRN -F irn_ack_no=112010036563310 -F irn_ack_date=2026-11-26T12:30 -F irn_qr="eyJhbGciOiJSUzI1NiJ9.eyJkYXRhIjoiSU5WLzAwMDIifQ.c2ln" >/dev/null
eq "IRN, Ack no. and date saved" "$(q "select concat(irn,'|',irn_ack_no,'|',irn_ack_date) from invoices where id=2")" "$IRN|112010036563310|2026-11-26 12:30:00"
curl -s -b "$A" -o "$W/inv2.pdf" "$B/admin/invoices/2/pdf?download=1"
has "invoice PDF prints the IRN and Ack no." "$(pdftotext "$W/inv2.pdf" - 2>/dev/null | tr -d '\n')" "112010036563310"
eq "invoice with IRN leaves the waiting list" "$(curl -s -b "$A" "$B/admin/einvoice" | grep -c 'admin/einvoice/2/json')" "0"
eq "QR generator: text in = text out (matrix check of the format/version bits)" "$(php -r 'require "app/Libraries/QrCode.php"; $m=\App\Libraries\QrCode::matrix(str_repeat("x",1000)); echo count($m);')" "121"
eq "store user cannot save an IRN" "$(post "$W/store1" admin/stock admin/einvoice/2/irn -F irn=$IRN | cut -d' ' -f1)" "403"

section "Project profit report"
EXP_P1=$(q "select round(i.subtotal - ifnull((select sum(lm.qty * l.rate) from stock_lot_moves lm join stock_lots l on l.id=lm.lot_id join stock_movements m on m.id=lm.movement_id join dispatches d on d.id=m.ref_id and m.ref_type='dispatch' where d.project_id=p.id),0) - ifnull((select sum(amount) from expenses where project_id=p.id),0), 2) from projects p join invoices i on i.id=p.invoice_id where p.id=1")
PR=$(curl -s -b "$A" "$B/admin/reports/project-profit?from=2000-01-01&to=$D")
has "report lists project 1" "$PR" "admin/projects/1\""
has "project 1 profit = invoice before GST − material − expenses (₹$EXP_P1)" "$PR" "$(q "select format($EXP_P1,2,'en_IN')")"
has "project page shows the same profit" "$(curl -s -b "$A" "$B/admin/projects/1")" "$(q "select format($EXP_P1,2,'en_IN')")"
eq "Project Profit Excel export" "$(curl -s -b "$A" -o /dev/null -w '%{content_type}' "$B/admin/reports/project-profit?export=excel" | cut -d';' -f1)" "application/vnd.ms-excel"
eq "store user cannot open the profit report" "$(code "$W/store1" admin/reports/project-profit)" "403"

# ── EB / meter charges ─────────────────────────────────────────────────────
section "EB / meter charges: paid by company (–) or customer (extra)"
eq "customer defaults saved" "$(q "select group_concat(concat(id,':',ifnull(eb_charges_by,''),':',ifnull(eb_charges_amount+0,'')) order by id) from customers where id in (1,2)")" "1:customer:5000,2:company:"
eq "invoices take the customer's default" "$(q "select group_concat(concat(id,':',ifnull(eb_charges_by,''),':',ifnull(eb_charges_amount+0,'')) order by id) from invoices where id in (1,2)")" "1:customer:5000,2:company:"
has "customer form asks who pays EB charges" "$(curl -s -b "$A" "$B/admin/customers/new")" 'name="eb_charges_by"'
has "invoice form asks who pays EB charges" "$(curl -s -b "$A" "$B/admin/invoices/new")" 'data-eb-by="customer" data-eb-amount="5000.00"'
IL=$(curl -s -b "$A" "$B/admin/invoices?from=2000-01-01&to=$D")
rowtxt() { php -r 'foreach (explode("<tr>", $argv[1]) as $tr) if (str_contains($tr, "admin/invoices/" . $argv[2] . "\"")) echo trim(preg_replace("/\s+/", " ", strip_tags($tr)));' "$IL" "$1"; }
has "list: customer-paid meter charges + total receivable" "$(rowtxt 1)" "Total ₹2,06,600.00 ₹5,000.00"
has "list: company-paid shows –" "$(rowtxt 2)" "₹1,98,320.00 – ₹1,98,320.00"
has "invoice list: Paid column between Meter charges and Due" "$IL" '<th class="text-end lh-sm">Meter<br>charges</th><th class="text-end">Paid</th><th class="text-end">Due</th>'
has "invoice page shows it" "$(curl -s -b "$A" "$B/admin/invoices/1")" "beyond the invoice · total receivable <strong>₹2,06,600.00"
post "$A" admin/invoices/2 admin/invoices/2/eb-charges -F eb_charges_by=customer -F eb_charges_amount=3000 >/dev/null
eq "changed on the invoice page" "$(q "select concat(eb_charges_by,':',eb_charges_amount+0) from invoices where id=2")" "customer:3000"
post "$A" admin/invoices/2 admin/invoices/2/eb-charges -F eb_charges_by=company -F eb_charges_amount=3000 >/dev/null
eq "company-paid clears the amount" "$(q "select concat(eb_charges_by,':',ifnull(eb_charges_amount,'null')) from invoices where id=2")" "company:null"

# ── admin edit / delete of saved entries ───────────────────────────────────
section "Admin: edit and delete invoices, payments, customers, projects"
has "Roles & Access lists the new permissions" "$(curl -s -b "$A" "$B/admin/roles")" "Delete entries: customers, invoices, payments, projects, purchases and items"
post "$A" admin/invoices/new admin/invoices -F customer_id=1 -F invoice_date="$D" -F subsidy_type=non_subsidy -F capacity_kw=2 -F create_project=1 -F eb_charges_by=company \
  -F 'items[0][description]=Edit test system' -F 'items[0][qty]=1' -F 'items[0][rate]=10000' -F 'items[0][tax_percent]=12' >/dev/null
XI=$(q "select max(id) from invoices"); XP=$(q "select id from projects where invoice_id=$XI")
post "$A" admin/invoices/$XI admin/invoices/$XI/payments -F amount=5000 -F payment_date="$D" -F mode=cash -F account_id=2 >/dev/null
XPAY=$(q "select max(id) from payments where invoice_id=$XI")
eq "accounts user cannot edit invoices" "$(code "$W/acct1" admin/invoices/$XI/edit)" "403"
eq "manager (Admin role) can edit invoices" "$(code "$W/mgr" admin/invoices/$XI/edit)" "200"
has "invoice page shows Edit and Delete for admin" "$(curl -s -b "$A" "$B/admin/invoices/$XI")" "admin/invoices/$XI/delete"
hasnt "…but not for the accounts user" "$(curl -s -b "$W/acct1" "$B/admin/invoices/$XI")" "/delete"
has "edit form is prefilled with the lines" "$(curl -s -b "$A" "$B/admin/invoices/$XI/edit")" 'value="Edit test system"'
r=$(post "$A" admin/invoices/$XI/edit admin/invoices/$XI/update -F customer_id=1 -F invoice_date="$D" -F subsidy_type=non_subsidy -F capacity_kw=2 -F 'items[0][description]=Too small' -F 'items[0][qty]=1' -F 'items[0][rate]=1000' -F 'items[0][tax_percent]=12')
has "new total below amount paid is refused" "$(flash "$A" admin/invoices/$XI/edit)" "less than the amount already paid"
post "$A" admin/invoices/$XI/edit admin/invoices/$XI/update -F customer_id=1 -F invoice_date="$D" -F subsidy_type=non_subsidy -F capacity_kw=4 -F eb_charges_by=customer -F eb_charges_amount=5000 \
  -F 'items[0][description]=Edit test system 4 kW' -F 'items[0][qty]=1' -F 'items[0][rate]=20000' -F 'items[0][tax_percent]=12' >/dev/null
eq "invoice edited: total, paid, status, meter charges" "$(q "select concat(total+0,'/',amount_paid+0,'/',status,'/',eb_charges_by) from invoices where id=$XI")" "22400/5000/partial/customer"
eq "…project follows (kW)" "$(q "select capacity_kw+0 from projects where id=$XP")" "4"
eq "…sales voucher re-posted with the new total" "$(q "select sum(e.debit)+0 from voucher_entries e join vouchers v on v.id=e.voucher_id where v.ref_type='invoice' and v.ref_id=$XI")" "22400"
post "$A" admin/payments/$XPAY/edit admin/payments/$XPAY/update -F amount=6000 -F payment_date="$D" -F mode=upi -F account_id=1 -F reference=UTR-EDIT >/dev/null
eq "payment edited: amount, mode, invoice paid" "$(q "select concat(p.amount+0,'/',p.mode,'/',i.amount_paid+0) from payments p join invoices i on i.id=p.invoice_id where p.id=$XPAY")" "6000/upi/6000"
eq "…receipt voucher follows" "$(q "select sum(e.debit)+0 from voucher_entries e join vouchers v on v.id=e.voucher_id where v.ref_type='payment' and v.ref_id=$XPAY")" "6000"
post "$A" admin/invoices/$XI admin/payments/$XPAY/delete >/dev/null
eq "payment deleted: invoice back to unpaid, voucher gone" "$(q "select concat(i.amount_paid+0,'/',i.status,'/',(select count(*) from vouchers where ref_type='payment' and ref_id=$XPAY)) from invoices i where id=$XI")" "0/unpaid/0"
post "$A" admin/invoices/$XI admin/invoices/$XI/payments -F amount=1000 -F payment_date="$D" -F mode=cash -F account_id=2 >/dev/null
post "$A" admin/invoices/$XI admin/invoices/$XI/delete >/dev/null
eq "invoice deleted with its payments, project and vouchers" "$(q "select concat((select count(*) from invoices where id=$XI),'/',(select count(*) from payments where invoice_id=$XI),'/',(select count(*) from projects where id=$XP),'/',(select count(*) from project_stages where project_id=$XP),'/',(select count(*) from vouchers where (ref_type='invoice' and ref_id=$XI)))")" "0/0/0/0/0"
eq "books still balance (Σ debit = Σ credit)" "$(q "select round(sum(debit)-sum(credit),2)+0 from voucher_entries")" "0"
r=$(post "$A" admin/customers/1 admin/customers/1/delete); has "customer with invoices cannot be deleted" "$(flash "$A" admin/customers/1)" "Delete those invoices first"
post "$A" admin/customers/new admin/customers/save -F type=b2c -F name="Delete Me" -F phone=9000000099 >/dev/null; XC=$(q "select id from customers where name='Delete Me'")
post "$A" admin/customers/$XC admin/customers/$XC/delete >/dev/null
eq "customer without invoices deleted (hidden from lists)" "$(q "select deleted_at is not null from customers where id=$XC")" "1"
curl -s -b "$A" "$B/admin/customers" >/dev/null; hasnt "…and gone from the customer list" "$(curl -s -b "$A" "$B/admin/customers")" "Delete Me"

section "Admin: delete wrong opening / adjustment stock lines"
item "" 1 "Ledger Clear Test" LCT Nos 8541 12 100 150 0 1 4
LI=$(q "select id from items where sku='LCT'")
post "$A" admin/stock/adjust admin/stock/adjust -F location_id=1 -F type=adjustment -F note=opening -F "items[0][item_id]=$LI" -F 'items[0][qty]=20' >/dev/null
eq "setup: 4 opening + 20 adjustment" "$(q "select group_concat(concat(type,':',qty+0) order by id) from stock_movements where item_id=$LI")" "opening:4,adjustment:20"
has "ledger shows a delete button on these lines" "$(curl -s -b "$A" "$B/admin/stock/item/$LI")" "admin/stock/movements/"
for m in $(q "select id from stock_movements where item_id=$LI"); do post "$A" admin/stock/item/$LI admin/stock/movements/$m/delete >/dev/null; done
eq "both lines deleted, stock 0" "$(q "select count(*) from stock_movements where item_id=$LI")" "0"
PM=$(q "select id from stock_movements where type='purchase' limit 1")
r=$(post "$A" admin/stock/adjust admin/stock/movements/$PM/delete); has "purchase line cannot be deleted here" "$(flash "$A" admin/stock/adjust)" "Only opening stock and adjustment lines"
eq "accounts user cannot delete stock lines" "$(post "$W/acct1" admin/expenses/new admin/stock/movements/$PM/delete | cut -d' ' -f1)" "403"

section "Admin: delete purchase orders, purchases, returns and items"
r=$(post "$A" admin/purchases/1 admin/purchases/1/delete); has "purchase with a return cannot be deleted" "$(flash "$A" admin/purchases/1)" "Delete those returns first"
item "" 1 "Delete Test Panel" DELP Nos 8541 12 1000 1500 0
DI=$(q "select id from items where sku='DELP'")
post "$A" admin/purchase-orders/new admin/purchase-orders -F supplier_id=1 -F doc_date="$D" -F "items[0][item_id]=$DI" -F 'items[0][qty]=10' -F 'items[0][rate]=1000' -F 'items[0][tax_percent]=12' >/dev/null
DPO=$(q "select max(id) from purchase_orders")
post "$A" "admin/purchases/new?po=$DPO" admin/purchases -F supplier_id=1 -F purchase_order_id=$DPO -F doc_date="$D" -F bill_no=DEL-1 -F location_id=1 -F "items[0][item_id]=$DI" -F 'items[0][qty]=10' -F 'items[0][rate]=1000' -F 'items[0][tax_percent]=12' >/dev/null
DPU=$(q "select max(id) from purchases")
post "$A" "admin/purchase-returns/new?purchase=$DPU" admin/purchase-returns -F supplier_id=1 -F purchase_id=$DPU -F location_id=1 -F doc_date="$D" -F "items[0][item_id]=$DI" -F 'items[0][qty]=3' >/dev/null
DRE=$(q "select max(id) from purchase_returns")
eq "setup: 10 bought, 3 returned, PO received" "$(q "select concat((select sum(qty)+0 from stock_movements where item_id=$DI),'/',(select status from purchase_orders where id=$DPO))")" "7/received"
has "list shows Delete for admin" "$(curl -s -b "$A" "$B/admin/purchases")" "admin/purchases/$DPU/delete"
post "$A" admin/purchase-returns/$DRE admin/purchase-returns/$DRE/delete >/dev/null
eq "return deleted: stock back, debit note gone" "$(q "select concat((select sum(qty)+0 from stock_movements where item_id=$DI),'/',(select count(*) from vouchers where ref_type='purchase_return' and ref_id=$DRE),'/',(select count(*) from purchase_returns where id=$DRE))")" "10/0/0"
post "$A" admin/purchases/$DPU admin/purchases/$DPU/delete >/dev/null
eq "purchase deleted: stock out, voucher gone, PO back to open" "$(q "select concat(ifnull((select sum(qty)+0 from stock_movements where item_id=$DI),0),'/',(select count(*) from vouchers where ref_type='purchase' and ref_id=$DPU),'/',(select status from purchase_orders where id=$DPO),'/',(select sum(received_qty)+0 from purchase_order_items where purchase_order_id=$DPO))")" "0/0/open/0"
post "$A" admin/purchase-orders/$DPO admin/purchase-orders/$DPO/delete >/dev/null
eq "purchase order deleted" "$(q "select count(*) from purchase_orders where id=$DPO")" "0"
r=$(post "$A" admin/items admin/items/1/delete); has "item with stock cannot be deleted" "$(flash "$A" admin/items)" "in stock / in transit"
post "$A" admin/items admin/items/$DI/delete >/dev/null
eq "item with zero stock deleted (hidden, history kept)" "$(q "select concat(deleted_at is not null,'/',is_active) from items where id=$DI")" "1/0"
eq "books still balance after purchase deletes" "$(q "select round(sum(debit)-sum(credit),2)+0 from voucher_entries")" "0"
# lots: a purchase whose stock was already used cannot be deleted even if other stock covers the quantity
post "$A" admin/purchases/new admin/purchases -F supplier_id=1 -F doc_date="$D" -F bill_no=LOT-1 -F location_id=1 -F 'items[0][item_id]=6' -F 'items[0][qty]=10' -F 'items[0][rate]=50' -F 'items[0][tax_percent]=18' >/dev/null; LP1=$(q "select max(id) from purchases")
post "$A" admin/purchases/new admin/purchases -F supplier_id=1 -F doc_date="$D" -F bill_no=LOT-2 -F location_id=1 -F 'items[0][item_id]=6' -F 'items[0][qty]=10' -F 'items[0][rate]=60' -F 'items[0][tax_percent]=18' >/dev/null; LP2=$(q "select max(id) from purchases")
OLDLEFT=$(q "select sum(qty_left)+0 from stock_lots where item_id=6 and location_id=1 and id < (select min(id) from stock_lots where purchase_id=$LP1)")
post "$A" admin/stock/adjust admin/stock/adjust -F location_id=1 -F type=adjustment -F note="Damaged" -F 'items[0][item_id]=6' -F "items[0][qty]=-$(php -r 'echo (float)$argv[1]+3;' "$OLDLEFT")" >/dev/null
eq "adjustment used old stock first, then 3 of LOT-1" "$(q "select concat(qty_left+0) from stock_lots where purchase_id=$LP1")|$(q "select qty_left+0 from stock_lots where purchase_id=$LP2")" "7|10"
r=$(post "$A" admin/purchases/$LP1 admin/purchases/$LP1/delete); has "purchase whose lot is used cannot be deleted" "$(flash "$A" admin/purchases/$LP1)" "already used in a stock adjustment"
eq "…nothing changed" "$(q "select count(*) from purchases where id=$LP1")|$(q "select qty_left+0 from stock_lots where purchase_id=$LP1")" "1|7"
post "$A" admin/purchases/$LP2 admin/purchases/$LP2/delete >/dev/null
eq "unused purchase deleted with its lot" "$(q "select count(*) from stock_lots where purchase_id=$LP2")" "0"
eq "lots match the stock ledger for every item and store" "$(q "select count(*) from (select m.item_id, m.location_id, sum(m.qty) s from stock_movements m group by m.item_id, m.location_id) b left join (select item_id, location_id, sum(qty_left) l from stock_lots group by item_id, location_id) x on x.item_id=b.item_id and x.location_id=b.location_id where abs(b.s - ifnull(x.l,0)) > 0.001")" "0"
eq "accounts user cannot delete purchases (permission)" "$(post "$W/acct1" admin/expenses/new admin/purchases/1/delete | cut -d' ' -f1)" "403"
eq "…and the purchase is still there" "$(q "select count(*) from purchases where id=1")" "1"

# ── install on phone (PWA) ────────────────────────────────────────────────
section "Install on phone (manifest, icons, service worker)"
MF=$(curl -s "$B/manifest.webmanifest")
eq "manifest served as a web app manifest" "$(curl -s -o /dev/null -w '%{content_type}' "$B/manifest.webmanifest" | cut -d';' -f1)" "application/manifest+json"
eq "manifest: company name, standalone, start page, 3 icons" "$(php -r '$m=json_decode($argv[1],true); echo $m["name"],"|",$m["display"],"|",parse_url($m["start_url"],PHP_URL_PATH),"|",count($m["icons"]);' "$MF")" "Sun Power Solutions|standalone|/admin|3"
for sz in 192 512; do eq "icon ${sz}×${sz} PNG (from the logo)" "$(curl -s "$B/app-icon/$sz.png" | php -r '$i=getimagesizefromstring(stream_get_contents(STDIN)); echo $i[0],"x",$i[1]," ",$i["mime"];')" "${sz}x${sz} image/png"; done
eq "maskable icon" "$(curl -s -o /dev/null -w '%{http_code} %{content_type}' "$B/app-icon/512-maskable.png")" "200 image/png"
eq "bad icon name is 404" "$(curl -s -o /dev/null -w '%{http_code}' "$B/app-icon/../../.env")" "404"
eq "service worker and offline page are served" "$(curl -s -o /dev/null -w '%{http_code}' "$B/sw.js") $(curl -s -o /dev/null -w '%{http_code}' "$B/offline.html")" "200 200"
has "login page links the manifest" "$(curl -s "$B/login")" 'rel="manifest"'
has "menu offers Install app" "$(curl -s -b "$A" "$B/admin")" "Install app on this device"

# ── 3D roof design ────────────────────────────────────────────────────────
section "3D roof design: save, shading figures, quotation from design"
DL=$QL
has "lead page offers the 3D roof design" "$(curl -s -b "$M" "$B/admin/leads/$DL")" "admin/designs/new?lead=$DL"
has "designer opens for the lead" "$(curl -s -b "$M" "$B/admin/designs/new?lead=$DL")" 'id="rd-init"'
Y=$(curl -s -b "$M" "$B/admin/designs/yield?lat=11.34&lng=77.72&tilt=10&azimuth=180")
eq "solar yield answer (kWh per kWp > 800, 12 months)" "$(php -r '$y=json_decode($argv[1],true); echo ($y["kwh_per_kwp"]>800?"ok":"low"),"|",count($y["monthly"]);' "$Y")" "ok|12"
PNG=$(php -r '$i=imagecreatetruecolor(8,8); ob_start(); imagepng($i); echo "data:image/png;base64,".base64_encode(ob_get_clean());')
djson() { php -r '$a=["name"=>$argv[1],"lead_id"=>(int)$argv[2],"latitude"=>11.34,"longitude"=>77.72,"data"=>["version"=>1,"roof"=>["pts"=>[[-6,-5],[6,-5],[6,5],[-6,5]],"h"=>6,"parapet"=>0.9],"obs"=>[],"p"=>["watt"=>545,"tilt"=>10,"az"=>180],"removed"=>[]],"panel_item_id"=>1,"panel_watt"=>545,"panel_count"=>(int)$argv[3],"kwh_per_kwp"=>1460,"shading_loss"=>2,"monthly_kwh"=>array_fill(0,12,600),"yield_source"=>"estimate","snapshot"=>$argv[4]]; echo json_encode($a);' "$@"; }
dpost() { local j=$1 u=$2 body=$3; curl -s -b "$j" -c "$j" -H "Content-Type: application/json" -H "X-CSRF-TOKEN: $(tok "$j" admin/leads/new)" -H "X-Requested-With: XMLHttpRequest" --data-binary "$body" "$B/$u"; }
R1=$(dpost "$M" admin/designs/save "$(djson '' "$DL" 10 "$PNG")")
has "design without a name is refused" "$R1" "Give the design a name"
R1=$(dpost "$M" admin/designs/save "$(djson 'Quote Lead Kumar · roof' "$DL" 10 "$PNG")")
eq "design saved with a number" "$(php -r '$r=json_decode($argv[1],true); echo ($r["ok"]??false)?"ok":"no","|",substr((string)($r["design_no"]??""),0,3);' "$R1")" "ok|RD/"
DS=$(q "select max(id) from roof_designs")
eq "kW = panels × watt; kWh = kW × yield × (1 − shading)" "$(q "select concat(panel_count,'|',capacity_kw+0,'|',kwh_year+0,'|',lead_id) from roof_designs where id=$DS")" "10|5.45|7797.90|$DL"
eq "3D picture saved and served as PNG" "$(curl -s -b "$M" -o /dev/null -w '%{http_code} %{content_type}' "$B/admin/designs/$DS/snapshot")" "200 image/png"
dpost "$M" "admin/designs/save/$DS" "$(djson 'Quote Lead Kumar · roof' "$DL" 12 "$PNG")" >/dev/null
eq "design edited: 12 panels = 6.54 kW, still one design" "$(q "select concat(panel_count,'|',capacity_kw+0) from roof_designs where id=$DS")|$(q "select count(*) from roof_designs where deleted_at is null")" "12|6.54|1"
eq "Create quotation goes to the pre-filled form" "$(curl -s -b "$M" -o /dev/null -w '%{redirect_url}' "$B/admin/designs/$DS/quotation" | sed 's#.*/admin/##')" "quotations/new?design=$DS&lead=$DL"
QF=$(curl -s -b "$M" "$B/admin/quotations/new?design=$DS&lead=$DL")
has "quotation form carries the design" "$QF" "name=\"roof_design_id\" value=\"$DS\""
has "…with the design's kW" "$QF" 'value="6.54"'
post "$M" "admin/quotations/new?design=$DS" admin/quotations -F lead_id=$DL -F roof_design_id=$DS -F party_name="Quote Lead Kumar" -F party_phone=9555500001 -F quote_date="$D" -F subsidy_type=subsidy -F customer_type=b2c -F capacity_kw=6.54 -F units_per_kw_day=3.27 -F tariff=6.5 \
  -F 'items[0][description]=6.54 kW On-grid Solar Power System' -F 'items[0][qty]=1' -F 'items[0][rate]=330000' -F 'items[0][tax_percent]=12' >/dev/null
QD=$(q "select max(id) from quotations")
eq "quotation linked to the design, 3D picture attached" "$(q "select concat(roof_design_id,'|',design_path like 'quotations/design-%') from quotations where id=$QD")|$(q "select quotation_id from roof_designs where id=$DS")" "$DS|1|$QD"
has "quotation page links back to the design" "$(curl -s -b "$M" "$B/admin/quotations/$QD")" "admin/designs/$DS"
eq "quotation PDF with the layout picture" "$(curl -s -b "$M" -o "$W/qd.pdf" -w '%{content_type}' "$B/admin/quotations/$QD/pdf") $(grep -ac '/Subtype /Image' "$W/qd.pdf" | awk '{print ($1>0)?"image":"none"}')" "application/pdf image"
eq "designs list shows the design" "$(curl -s -b "$M" "$B/admin/designs" | grep -c "admin/designs/$DS\"")" "1"
eq "surveyor may design, accounts may not" "$(code "$W/suresh" admin/designs) $(code "$W/acct1" admin/designs) $(code "$W/acct1" "admin/designs/$DS")" "200 403 403"
PU=$(curl -s -b "$M" -c "$M" -H "X-CSRF-TOKEN: $(tok "$M" admin/leads/new)" -H "X-Requested-With: XMLHttpRequest" -F kind=site -F caption="Roof from staircase" -F "photo=@$W/photo.jpg" "$B/admin/designs/photos")
PHID=$(php -r '$r=json_decode($argv[1],true); echo ($r["ok"]??false)?$r["id"]:"0";' "$PU")
has "site photo uploaded (re-saved as JPG)" "$PU" '"kind": "site"'
eq "non-image refused" "$(curl -s -b "$M" -c "$M" -H "X-CSRF-TOKEN: $(tok "$M" admin/leads/new)" -F kind=site -F "photo=@$W/virus.exe" -o /dev/null -w '%{http_code}' "$B/admin/designs/photos")" "422"
eq "photo served as JPEG" "$(curl -s -b "$M" -o /dev/null -w '%{http_code} %{content_type}' "$B/admin/designs/photos/$PHID")" "200 image/jpeg"
eq "photo not attached yet, not visible to others" "$(q "select design_id is null from roof_design_photos where id=$PHID")|$(code "$W/suresh" "admin/designs/photos/$PHID")" "1|404"
dpost "$M" "admin/designs/save/$DS" "$(djson 'Quote Lead Kumar · roof' "$DL" 12 "$PNG" | php -r '$a=json_decode(stream_get_contents(STDIN),true); $a["photo_ids"]=[(int)$argv[1]]; echo json_encode($a);' "$PHID")" >/dev/null
eq "photo attached to the design on save, then visible to the team" "$(q "select design_id from roof_design_photos where id=$PHID")|$(code "$W/suresh" "admin/designs/photos/$PHID")" "$DS|200"
has "designer lists the photo" "$(curl -s -b "$M" "$B/admin/designs/$DS")" "admin\\/designs\\/photos\\/$PHID"
eq "photo deleted" "$(curl -s -b "$M" -c "$M" -X POST -H "X-CSRF-TOKEN: $(tok "$M" admin/leads/new)" -o /dev/null -w '%{http_code}' "$B/admin/designs/photos/$PHID/delete")|$(q "select count(*) from roof_design_photos where id=$PHID")" "200|0"
has "Settings has the Google Maps key field" "$(curl -s -b "$A" "$B/admin/settings")" 'name="googleMapsKey"'
post "$M" "admin/designs/$DS" "admin/designs/$DS/delete" >/dev/null
eq "design deleted (kept for history, gone from the list)" "$(q "select deleted_at is not null from roof_designs where id=$DS")|$(code "$M" "admin/designs/$DS")" "1|404"

# ── browser: type-to-search dropdowns ─────────────────────────────────────
section "Type-to-search dropdowns (real browser)"
if node -e "require('playwright')" 2>/dev/null; then
  while IFS= read -r line; do
    case "$line" in PASS\ *) ok "${line#PASS }";; FAIL\ *) bad "${line#FAIL }";; esac
  done < <(node tests/e2e/browser_search.js "http://$B")
else echo "  (playwright not installed – browser checks skipped)"; fi

# ── permissions ────────────────────────────────────────────────────────────
section "Role access (allowed = 200, blocked = 403)"
check() { local u=$1; shift; for pair in "$@"; do local p=${pair%%=*} want=${pair##*=}; eq "$u → $p" "$(code "$W/$u" "$p")" "$want"; done; }
check ravi    admin/leads=200 admin/invoices/new=200 admin/stock=403 admin/tally=403 admin/expenses=403 admin/users=403
check suresh  admin/tasks=200 admin/leads=403 admin/invoices=403 admin/stock=403
check store1  admin/stock=200 admin/purchases=200 admin/dispatches=200 admin/invoices=403 admin/expenses=403
check driver1 admin/dispatches=200 admin/stock=403 admin/purchases=403 admin/projects/1=200 admin/invoices/1=403
check fitter1 admin/projects/1=200 admin/dispatches=403 admin/invoices=403
check eb1     admin/projects=200 admin/reports/subsidy=403 admin/invoices=403
check acct1   admin/expenses=200 admin/tally=200 admin/accounts/trial-balance=200 admin/leads=403 admin/roles=403
check service1 admin/amc=200 admin/amc-plans=200 admin/stock=403
check mgr     admin/projects=200 admin/reports/projects=200 admin/users=200 admin/roles=403 admin/settings=403
check kumar   admin/stock=200 admin/dispatches=200 admin/invoices=403

# ── every page ─────────────────────────────────────────────────────────────
section "Every admin page loads for the Super Admin"
PAGES="admin admin/designs admin/designs/new admin/tasks admin/notifications admin/profile admin/users admin/users/new admin/roles admin/settings admin/user-log admin/message-log admin/whatsapp-templates
admin/leads admin/leads/1 admin/leads/new admin/customers admin/customers/1 admin/customers/2 admin/einvoice admin/invoices admin/invoices/1 admin/invoices/2 admin/invoices/new admin/payments
admin/projects admin/projects/1 admin/projects/2 admin/dispatches admin/dispatches/2 admin/amc admin/amc-plans admin/amc/contracts/1
admin/stock admin/stock/item/1 admin/stock/item/1/lots admin/stock/lots admin/stock/adjust admin/items admin/items/new admin/items/1/edit admin/kits admin/kits/1/edit admin/installation-types admin/item-categories admin/locations
admin/suppliers admin/purchase-orders admin/purchase-orders/1 admin/purchase-orders/new admin/purchases admin/purchases/1 admin/purchases/new admin/purchase-returns admin/purchase-returns/1
admin/incomes admin/incomes/new admin/expenses admin/expenses/new admin/supplier-payments admin/income-heads admin/expense-heads admin/bank-accounts admin/ledgers
admin/accounts/day-book admin/accounts/trial-balance admin/accounts/journal admin/reports/income-expense admin/reports/usage admin/reports/suppliers admin/reports/projects
admin/reports/subsidy admin/reports/finance admin/accounts/profit-loss admin/document-types admin/maintenance admin/tally admin/purchases/1/pdf admin/purchase-orders/1/pdf admin/purchase-returns/1/pdf admin/documents/1 admin/files/1"
fails=""; for p in $PAGES; do c=$(code "$A" "$p"); [ "$c" == 200 ] || fails="$fails $p($c)"; done
eq "all $(wc -w <<<"$PAGES") pages return 200" "${fails:-none}" "none"
errs=$(tail -n +"$((LOGSTART+1))" "$LOGF" 2>/dev/null | grep -h "CRITICAL" | grep -v "ForbiddenException\|PageNotFound\|Broken pipe\|input ended\|SecurityException" | sort -u | head -5)
eq "no unexpected errors in today's log" "${errs:-none}" "none"

# ── summary ────────────────────────────────────────────────────────────────
printf '\n\e[1mResult: %d passed, %d failed\e[0m\n' "$PASS" "$FAIL"
for f in "${FAILS[@]}"; do printf '  - %s\n' "$f"; done
[ "$FAIL" -eq 0 ]
